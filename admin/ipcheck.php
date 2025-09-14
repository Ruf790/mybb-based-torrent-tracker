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

 

  
  include_once INC_PATH . '/functions_ratio.php';
  include_once INC_PATH . '/functions_icons.php';
  define ('I_CHECK_VERSION', '0.7 by xam');
  stdhead ('Duplicate IP users', true, '');
  if (($_GET['action'] == 'ban' AND !empty ($_POST['userid'])))
  {
    ($db->sql_query ('UPDATE users SET enabled = \'no\' WHERE id IN (' . implode (', ', $_POST['userid']) . ')'));
  }

  print '<table cellpadding=5 align=center border=0 width=100%>';
  print '<tr><td colspan="10" align="left" class="colhead">' . ($_GET['action'] == 'ban' ? '<font color=red><strong>Total ' . $db->affected_rows () . ' user(s) has been banned!</strong></font>' : 'Duplicate IP users (by IP)') . '</td></tr>
  ';
  ($res = $db->sql_query ('SELECT count(*) AS dupl, regip, usergroup FROM users WHERE enabled = \'yes\' AND regip <> \'\' AND regip <> \'127.0.0.0\' GROUP BY regip ORDER BY dupl DESC, regip'));
  print '<tr align=center>
  <td class=subheader align=left>Username</td>
 <td class=subheader align=left>Email</td>
 <td class=subheader>Registered</td>
 <td class=subheader>Last Access</td>
 <td class=subheader>Down</td>
 <td class=subheader>Up</td>
 <td class=subheader>Ratio</td>
 <td class=subheader>Ip Address</td>
 <td class=subheader>Peer</td>
 <td class=subheader>Ban</td></tr>
';
  echo '<form method="post" action="' . $_this_script_ . '&action=ban">';
  $uc = 0;
  while ($ras = $db->fetch_array ($res))
  {
    if ($ras['dupl'] <= 1)
    {
      continue;
    }

    //if ($ras['usergroup'] == UC_SYSOP)
    //{
    //  continue;
    //}

    if ($ip != $ras['ip'])
    {
      ($ros = $db->sql_query ("
	  SELECT 
	  u.*,
	  p.canupload,
	  g.cansettingspanel, g.namestyle,
	  pp.ip as peerip
	  FROM users u
	  LEFT JOIN ts_u_perm p ON (u.id=p.userid) 
	  LEFT JOIN usergroups g ON (u.usergroup=g.gid)
	  LEFT JOIN peers pp ON (u.ip=pp.ip&&u.id=pp.userid)
	  WHERE u.ip = " . $db->escape_string ($ras['ip']) . "
	  GROUP BY u.username
	  "));
      $num2 = $db->num_rows ($ros);
      if (1 < $num2)
      {
        ++$uc;
        while ($arr = $db->fetch_array ($ros))
        {
          if ($arr['cansettingspanel'] == 'yes')
          {
            continue;
          }

          if ($arr['added'] == '0000-00-00 00:00:00')
          {
            //$arr['added'] = '-';
          }

          if ($arr['lastactive'] == '0000-00-00 00:00:00')
          {
            //$arr['lastactive'] = '-';
          }

          if ($arr['downloaded'] != 0)
          {
            $ratio = number_format ($arr['uploaded'] / $arr['downloaded'], 2);
          }
          else
          {
            $ratio = '---';
          }

          $ratio = '<font color=' . get_ratio_color ($ratio) . ('' . '>' . $ratio . '</font>');
          $uploaded = mksize ($arr['uploaded']);
          $downloaded = mksize ($arr['downloaded']);
          $added = my_datee ($dateformat, $arr['added']) . '<br />' . my_datee ($timeformat, $arr['added']);
          $lastactive = my_datee ($dateformat, $arr['lastactive']) . '<br />' . my_datee ($timeformat, $arr['lastactive']);
          if ($uc % 2 == 0)
          {
            $utc = '';
          }
          else
          {
            $utc = ' bgcolor="ECE9D8"';
          }

          print '' . '<tr' . $utc . '><td align=left><b><a href=\'' . $BASEURL . '/userdetails.php?id=' . $arr['id'] . '\'>' . format_name($arr['username'], $arr['namestyle']) . '</b></a>' . get_user_icons ($arr) . ('' . '</td>
				  <td align=left>' . $arr['email'] . '</td>
				  <td align=center>' . $added . '<br /></td>
				  <td align=center>' . $lastactive . '<br /></td>
				  <td align=center>' . $downloaded . '</td>
				  <td align=center>' . $uploaded . '</td>
				  <td align=center>' . $ratio . '</td>
				  <td align=center><a href="' . $BASEURL . '/redirector.php?url=http://www.whois.sc/' . $arr['ip'] . '" target="_blank">' . $arr['ip'] . '</a></td>
<td align=center>') . (!empty ($arr['peerip']) ? '<font color=green>YES</font>' : '<font color=red>NO</font>') . '</td>
				  <td align=center><input type=checkbox name=userid[] value=' . (int)$arr['id'] . '></td></tr>
';
          $ip = $arr['ip'];
        }

        continue;
      }

      echo '<tr><td colspan=10 align=left>Nothing Found!</td></tr>';
      continue;
    }
  }

  echo '<tr><td colspan=10 align=right><input type=submit value="ban selected" class=button> <input type="button" value="check all" class=button onClick="this.value=check(form)"></td></tr>';
  print '</form></table><br />';
  print '<table cellpadding=5 align=center border=0 width=100%>';
  print '<tr><td colspan="10" align="left" class="colhead">' .  ($_GET['action'] == 'ban' ? '<font color=red><strong>Total ' . $db->affected_rows () . ' user(s) has been banned!</strong></font>' : 'Duplicate IP users (by Password)') . '</td></tr>
  ';
  print '<tr align=center>
<td class=subheader align=left>Username</td>
 <td class=subheader align=left>Email</td>
 <td class=subheader>Registered</td>
 <td class=subheader>Last Access</td>
 <td class=subheader>Down</td>
 <td class=subheader>Up</td>
 <td class=subheader>Ratio</td>
 <td class=subheader>Ip Address</td>
 <td class=subheader>Peer</td>
 <td class=subheader>Ban</td></tr>
';
  echo '<form method="post" action="' . $_this_script_ . '&action=ban">';
  ($res = $db->sql_query ('SELECT count(*) AS dupl, passhash, usergroup FROM users WHERE 1=1 GROUP BY passhash ORDER BY dupl DESC, passhash'));
  $uc = 0;
  while ($ras = $db->fetch_array ($res))
  {
    if ($ras['dupl'] <= 1)
    {
      continue;
    }

    //if ($ras['usergroup'] == UC_STAFFLEADER)
    //{
      //continue;
    //}

    if ($passhash != $ras['passhash'])
    {
      ($ros = $db->sql_query ('SELECT  u.*, p.canupload, g.cansettingspanel, 
	  g.namestyle, pp.ip as peerip FROM users u 
	  LEFT JOIN ts_u_perm p ON (u.id=p.userid) 
	  LEFT JOIN usergroups g ON (u.usergroup=g.gid)  
	  LEFT JOIN peers pp ON (u.ip=pp.ip AND u.id=pp.userid) WHERE u.passhash=\'' . $ras['passhash'] . '\' GROUP BY u.username ORDER BY u.id DESC'));
      $num2 = $db->num_rows ($ros);
      if (1 < $num2)
      {
        ++$uc;
        while ($arr =$db->fetch_array ($ros))
        {
          if ($arr['cansettingspanel'] == 'yes')
          {
            continue;
          }

          if ($arr['added'] == '0000-00-00 00:00:00')
          {
            $arr['added'] = '-';
          }

          if ($arr['lastactive'] == '0000-00-00 00:00:00')
          {
            $arr['lastactive'] = '-';
          }

          if ($arr['downloaded'] != 0)
          {
            $ratio = number_format ($arr['uploaded'] / $arr['downloaded'], 2);
          }
          else
          {
            $ratio = '---';
          }

          $ratio = '<font color=' . get_ratio_color ($ratio) . ('' . '>' . $ratio . '</font>');
          $uploaded = mksize ($arr['uploaded']);
          $downloaded = mksize ($arr['downloaded']);
          $added = my_datee ($dateformat, $arr['added']) . '<br />' . my_datee ($timeformat, $arr['added']);
          $lastactive = my_datee ($dateformat, $arr['lastactive']) . '<br />' . my_datee ($timeformat, $arr['lastactive']);
          if ($uc % 2 == 0)
          {
            $utc = '';
          }
          else
          {
            $utc = ' bgcolor="ECE9D8"';
          }

          print '' . '<tr' . $utc . '><td align=left><b><a href=\'' . $BASEURL . '/userdetails.php?id=' . $arr['id'] . '\'>' . format_name($arr['username'], $arr['namestyle']) . '</b></a>' . get_user_icons ($arr) . '<br />' . $arr['passhash'] . ('' . '</td>
				  <td align=left>' . $arr['email'] . '</td>
				  <td align=center>' . $added . '<br /></td>
				  <td align=center>' . $lastactive . '<br /></td>
				  <td align=center>' . $downloaded . '</td>
				  <td align=center>' . $uploaded . '</td>
				  <td align=center>' . $ratio . '</td>
				  <td align=center><a href="' . $BASEURL . '/redirector.php?url=http://www.whois.sc/' . $arr['ip'] . '" target="_blank">' . $arr['ip'] . '</a></td>
<td align=center>') . (!empty ($arr['peerip']) ? '<font color=green>YES</font>' : '<font color=red>NO</font>') . '</td>
				  <td align=center><input type=checkbox name=userid[] value=' . (int)$arr['id'] . '></td></tr>
';
          $passhash = $arr['passhash'];
        }

        continue;
      }

      echo '<tr><td colspan=10 align=left>Nothing Found!</td></tr>';
      continue;
    }
  }

  echo '<tr><td colspan=10 align=right><input type=submit value="ban selected" class=button> <input type="button" value="check all" class=button onClick="this.value=check(form)"></td></tr>';
  print '</form></table>';
  stdfoot ();
?>
