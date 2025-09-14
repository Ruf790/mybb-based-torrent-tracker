<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  require_once 'global.php';
  gzip ();

  
  maxsysop ();
  parked ();
  define ('CU_VERSION', '0.7');
  $lang->load ('checkuser');
  $id = 0 + $_GET['id'];
  int_check ($id, true);
  ($r = $db->sql_query ('SELECT u.*, c.flagpic FROM users u LEFT JOIN countries c ON (u.country=c.id) WHERE u.status = \'pending\' AND u.id = ' . $db->sqlesc ($id)));
  ($user = mysqli_fetch_array ($r) OR stderr ($lang->global['error'], $lang->global['nouserid']));
  if ((!is_mod ($usergroups) AND $user['invited_by'] != $CURUSER['id']))
  {
    print_no_permission ();
  }

  if ($user['added'] == '0000-00-00 00:00:00')
  {
    $joindate = $lang->checkuser['na'];
  }
  else
  {
    require_once INC_PATH . '/functions_mkprettytime.php';
    $joindate = my_datee ('relative', $user['added']) . ' (' . mkprettytime (TIMENOW - $user['added']) . ')';
  }

  if ($user['country'])
  {
    $country = '<td class=embedded><img src=' . $pic_base_url . ('' . 'flag/' . $user['flagpic'] . ' alt="' . $user['name'] . '" title="' . $user['name'] . '" style=\'margin-left: 8pt\'></td>');
  }

  stdhead (sprintf ($lang->checkuser['details'], $user['username']));
  $enabled = $user['enabled'] == 'yes';
  print '<p>' . '
  
  <div class="container mt-3">
  <tr>
  <td class=embedded><h1 style=\'margin:0px\'>' . sprintf ($lang->checkuser['details'], $user['username']) . ('' . '</h1></td>' . $country . '</tr>
  </p></div><br />
');
  if (!$enabled)
  {
    //print $lang->global['accountdisabled'];
	
	 print '<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$lang->global['accountdisabled'].'
	</div>
	 </div>
		</div>';
	
	
  }

  echo '
  
  
  <div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
  
  
<tr><td class=rowhead width=10%>';
  echo $lang->checkuser['joindate'];
  echo '</td><td align=left width=90%>';
  echo $joindate;
  echo '</td></tr>
<tr><td class=rowhead width=10%>';
  echo '</br>'.$lang->checkuser['email'];
  echo '</td><td align=left width=90%><a href=mailto:';
  echo $user['email'];
  echo '>';
  echo $user['email'];
  echo '</a></td></tr>
';
  if ((is_mod ($usergroups) AND $user['ip'] != ''))
  {
    print '</br><tr><td class=rowhead width=1%>' . $lang->checkuser['ip'] . ('' . '</td><td align=left width=99%>' . $user['ip'] . '</td></tr>');
  }

  print '</br><tr><td class=rowhead width=1%>' . $lang->checkuser['status'] . '</td><td align=left width=99%>' . ($user['status'] == 'pending' ? '<span class="badge bg-danger">' . $lang->checkuser['pending'] . '</span>' : '<font color=#1f7309>' . $lang->checkuser['confirmed'] . '</font>') . '</td></tr>';
  print '
	 
		</div>
 </div>
 </div>';
  stdfoot ();
?>
