<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function show_fn_errors ()
  {
    global $errors;
    global $lang;
    if (0 < count ($errors))
    {
      $error = implode ('<br />', $errors);
      echo '
			<table class="main" border="1" cellspacing="0" cellpadding="5" width="100%">
			<tr>
				<td class="thead">
					' . $lang->global['error'] . '
				</td>
			</tr>
			<tr>
				<td>
					<font color="red">
						<strong>
							' . $error . '
						</strong>
					</font>
				</td>
			</tr>
			</table>
			<br />
		';
    }

  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('FNC_VERSION', '1.0 by xam');
  $do = (isset ($_GET['do']) ? $_GET['do'] : (isset ($_POST['do']) ? $_POST['do'] : ''));
  $lang->load ('findnotconnectable');
  $PMSEND = false;
  $errors = array ();
  $str = '';
  if ((($do == 'delete' AND is_valid_id ($_GET['id'])) AND $DelID = intval ($_GET['id'])))
  {
    ($db->sql_query ('DELETE FROM notconnectablepmlog WHERE id = \'' . $DelID . '\'') OR sqlerr (__FILE__, 29));
    unset ($do);
  }

  if ($do == 'pm2')
  {
    if (is_array ($_POST['userids']))
    {
      $msg = trim ($lang->findnotconnectable['msg']);
      if ($msg)
      {
        require_once INC_PATH . '/functions_pm.php';
        foreach ($_POST['userids'] as $userid)
        {
          if (is_valid_id ($userid))
          {
            send_pm ($userid, $msg, $lang->findnotconnectable['subject']);
            continue;
          }
        }

        ($db->sql_query ('INSERT INTO notconnectablepmlog VALUES (NULL, \'' . $CURUSER['id'] . '\', NOW())') OR sqlerr (__FILE__, 48));
        $PMSEND = true;
        unset ($do);
      }
      else
      {
        $errors[] = $lang->findnotconnectable['error1'];
        $do = 'showlist';
      }
    }
    else
    {
      $errors[] = $lang->findnotconnectable['error3'];
      $do = 'showlist';
    }
  }

  if ($do == 'showlist')
  {
    ($query = $db->sql_query ('SELECT DISTINCT id FROM peers WHERE connectable = \'no\''));
    $count = $db->num_rows ($query);
    //list ($pagertop, $pagerbottom, $limit) = pager ($perpage, $count, $_this_script_ . '&amp;do=showlist&amp;');
    ($query = $db->sql_query ('SELECT DISTINCT p.torrent, p.userid, p.ip, p.port, p.seeder, p.agent, t.name, u.username, g.namestyle 
	FROM peers p LEFT JOIN torrents t ON (p.torrent=t.id) 
	LEFT JOIN users u ON (p.userid=u.id) 
	LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE p.connectable = \'no\' ORDER BY u.username ' . $limit));
    if (0 < $db->num_rows ($query))
    {
      $str .= $pagertop . '
		<form method="POST" action="' . $_this_script_ . '&amp;do=pm2" name="userlist">
		<table width="100%" align="center" border="0" cellpadding="5" cellspacing="0">
			<tr>
				<td class="thead" colspan="6"><span style="float: right;">' . sprintf ($lang->findnotconnectable['showlist3'], ts_nf ($count)) . '</span>' . $lang->findnotconnectable['showlist2'] . '</td>
			</tr>
			<tr>
				<td class="subheader" width="15%" align="left">' . $lang->findnotconnectable['username'] . '</td>
				<td class="subheader" width="40%" align="left">' . $lang->findnotconnectable['torrent'] . '</td>
				<td class="subheader" width="15%" align="left">' . $lang->findnotconnectable['ip'] . '</td>
				<td class="subheader" width="20%" align="left">' . $lang->findnotconnectable['client'] . '</td>
				<td class="subheader" width="5%" align="center">' . $lang->findnotconnectable['seeder'] . '</td>
				<td class="subheader" width="5%" align="center"><input checkall="group1" onclick="javascript: return select_deselectAll (\'userlist\', this, \'group1\');" type="checkbox" /></td>
			</tr>
		';
      while ($Users = mysqli_fetch_assoc ($query))
      {
        $str .= '
			<tr>
				<td width="15%" align="left"><a href="' . ts_seo ($Users['userid'], $Users['username']) . '">' . get_user_color ($Users['username'], $Users['namestyle']) . '</a></td>
				<td width="40%" align="left"><a href="' . $BASEURL . '/details.php?id=' . $Users['torrent'] . '">' . cutename ($Users['name'], 60) . '</a></td>
				<td width="15%" align="left">' . htmlspecialchars_uni ($Users['ip']) . ':' . htmlspecialchars_uni ($Users['port']) . '</td>
				<td width="20%" align="left">' . htmlspecialchars_uni ($Users['agent']) . '</td>
				<td width="5%" align="center">' . htmlspecialchars_uni ($Users['seeder']) . '</td>
				<td width="5%" align="center"><input checkme="group1" type="checkbox" name="userids[]" value="' . $Users['userid'] . '" /></td>
			</tr>
			';
      }

      $str .= '
			<tr>
				<td colspan="6" align="right"><input type="submit" value="' . $lang->findnotconnectable['pm3'] . '" /></td>
			</tr>
		</form>
		</table>
		' . $pagerbottom;
    }
    else
    {
      $errors[] = $lang->findnotconnectable['error2'];
      unset ($do);
    }
  }

  if ($do == 'pm')
  {
    if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
    {
      $msg = trim ($_POST['msg']);
      if ($msg)
      {
        ($query = $db->sql_query ('SELECT DISTINCT userid FROM peers WHERE connectable = \'no\''));
        if (0 < $db->num_rows ($query))
        {
          require_once INC_PATH . '/functions_pm.php';
          while ($PM = mysqli_fetch_assoc ($query))
          {
            send_pm ($PM['userid'], $msg, $lang->findnotconnectable['subject']);
          }

          ($db->sql_query ('INSERT INTO notconnectablepmlog VALUES (NULL, \'' . $CURUSER['id'] . '\', NOW())'));
          $PMSEND = true;
        }
        else
        {
          $errors[] = $lang->findnotconnectable['error2'];
        }
      }
      else
      {
        $errors[] = $lang->findnotconnectable['error1'];
      }
    }

    if ($PMSEND == false)
    {
      $str .= '
		<form method="post" action="' . $_this_script_ . '&amp;do=pm">
			<table width="100%" align="center" border="0" cellpadding="5" cellspacing="0">
				<tr>
					<td class="thead">' . $lang->findnotconnectable['pm2'] . '</td>
				</tr>
				<tr>
					<td align="center">
						<textarea name="msg" cols="120" rows="17">' . $lang->findnotconnectable['msg'] . '</textarea>
					</td>
				</tr>
				<tr>
					<td align="center" class="subheader">
						<input type="submit" value="' . $lang->findnotconnectable['pm'] . '" /> <input type="reset" value="' . $lang->findnotconnectable['reset'] . '" />
					</td>
				</tr>
			</table>
		</form>
		';
    }
    else
    {
      unset ($do);
    }
  }

  if (!$do)
  {
    $logs = '';
    ($Query = $db->sql_query ('SELECT n.id, n.user, n.date, u.username, g.namestyle FROM notconnectablepmlog n LEFT JOIN users u ON (n.user=u.id) LEFT JOIN usergroups g ON (u.usergroup=g.gid) ORDER BY date DESC') OR sqlerr (__FILE__, 180));
    if (0 < $db->num_rows ($Query))
    {
      while ($Log = mysqli_fetch_assoc ($Query))
      {
        $logs .= '
			<tr>
				<td><a href="' . ts_seo ($Log['user'], $Log['username']) . '">' . get_user_color ($Log['username'], $Log['namestyle']) . '</a></td>
				<td>' . my_datee ($dateformat, $Log['date']) . ' ' . my_datee ($timeformat, $Log['date']) . '</td>
				<td><a href="' . $_this_script_ . '&amp;do=delete&amp;id=' . $Log['id'] . '">' . $lang->findnotconnectable['delete'] . '</a></td>
			</tr>
			';
      }
    }
    else
    {
      $logs .= '
		<tr>
			<td colspan="3">' . $lang->findnotconnectable['nolog'] . '</td>
		</tr>
		';
    }

    $str .= '	
	<table width="100%" align="center" border="0" cellpadding="5" cellspacing="0">
		<tr>
			<td class="thead" colspan="3">' . $lang->findnotconnectable['log'] . '</td>
		</tr>
		<tr>
			<td class="subheader">' . $lang->findnotconnectable['sender'] . '</td>
			<td class="subheader">' . $lang->findnotconnectable['date'] . '</td>
			<td class="subheader">' . $lang->findnotconnectable['action'] . '</td>
		</tr>
		' . $logs . '
	</table>
	';
  }

  $str = '
<div style="margin-bottom: 3px;">
	' . (!$do ? '' : '
	<input type="button" value="' . $lang->findnotconnectable['home'] . '" onclick="jumpto(\'' . $_this_script_ . '\'); return false;" /> ') . '
	<input type="button" value="' . $lang->findnotconnectable['pm'] . '" onclick="jumpto(\'' . $_this_script_ . '&amp;do=pm\'); return false;" /> 
	<input type="button" value="' . $lang->findnotconnectable['showlist'] . '" onclick="jumpto(\'' . $_this_script_ . '&amp;do=showlist\'); return false;" />
</div>' . $str;
  stdhead ($lang->findnotconnectable['head']);
  show_fn_errors ();
  echo $str;
  stdfoot ();
?>
