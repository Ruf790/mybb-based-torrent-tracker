<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function show_unbaniprequest_errors ()
  {
    global $error;
    global $lang;
    if (0 < count ($error))
    {
      $errors = implode ('<br />', $error);
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
							' . $errors . '
						</strong>
					</font>
				</td>
			</tr>
			</table>
			<br />
		';
    }

  }

  require_once 'global.php';
  gzip ();

  define ('UIR_VERSION', ' v.0.6');
  $lang->load ('unbaniprequest');
  $userip = ($_POST['ip'] ? $_POST['ip'] : getip ());
  ($query = $db->sql_query ('SELECT id FROM loginattempts WHERE ip = ' . $db->sqlesc ($userip) . ' AND banned = \'yes\' LIMIT 1'));
  if ($db->num_rows ($query) < 1)
  {
    stderr ($lang->unbaniprequest['error']);
  }

  ($query = $db->sql_query ('SELECT id FROM unbanrequests WHERE ip = ' . $db->sqlesc ($userip) . ' OR realip = ' . $db->sqlesc ($userip) . ' LIMIT 1'));
  if (0 < $db->num_rows ($query))
  {
    stderr ($lang->unbaniprequest['error2']);
  }

  if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
  {
    $error = array ();
    $email = trim ($_POST['email']);
    $comment = trim ($_POST['comment']);
    if (!check_email ($email))
    {
      $error[] = $lang->unbaniprequest['error3'];
    }

    require_once INC_PATH . '/functions_EmailBanned.php';
    if (emailbanned ($email))
    {
      $error[] = $lang->unbaniprequest['error4'];
    }

    if (strlen ($comment) < 10)
    {
      $error[] = $lang->unbaniprequest['error5'];
    }

    if (count ($error) == 0)
    {
      ($query = $db->sql_query ('INSERT INTO unbanrequests (ip, realip, email, comment, added) VALUES (' . $db->sqlesc ($userip) . ', ' . $db->sqlesc (getip ()) . ', ' . $db->sqlesc ($email) . ', ' . $db->sqlesc ($comment) . ', '.TIMENOW.')'));
      $newid = $db->insert_id ();
      if (($db->affected_rows () AND $newid))
      {
        ($query = $db->sql_query ('SELECT usergroups FROM staffpanel WHERE name = \'viewunbaniprequest\' OR filename = \'viewunbaniprequest.php\' LIMIT 1'));
        if (0 < $db->num_rows ($query))
        {
          $permusergroups = $db->fetch_field ($query, 'usergroups');
          if ($permusergroups)
          {
            $permusergroups = str_replace (array ('[', ']'), '', $permusergroups);
            if ($permusergroups)
            {
              ($query = $db->sql_query ('' . 'SELECT id FROM users WHERE usergroup IN (' . $permusergroups . ')'));
              if (0 < $db->num_rows ($query))
              {
                $subject = $lang->unbaniprequest['subject'];
                $msg = sprintf ($lang->unbaniprequest['message'], htmlspecialchars_uni ($userip), $BASEURL . '/admin/index.php?act=viewunbaniprequest#show_id' . $newid);
                require_once INC_PATH . '/functions_pm.php';
                while ($pmstaff = mysqli_fetch_assoc ($query))
                {
                  send_pm ($pmstaff['id'], $msg, $subject);
                }
              }
            }
          }
        }

        stdhead ($lang->unbaniprequest['head']);
        echo '
			<table width="100%" border="0" cellpadding="5" cellspacing="0">
				<tr>
					<td class="thead" align="center">' . $lang->unbaniprequest['title'] . '</td>
				</tr>
				<tr>
					<td>' . $lang->unbaniprequest['saved'] . '</td>
				</tr>
			</table>
			';
        stdfoot ();
        exit ();
      }
      else
      {
        stderr ($lang->global['dberror']);
      }
    }
  }

  stdhead ($lang->unbaniprequest['head']);
  show_unbaniprequest_errors ();
  echo '
<form method="POST" action="' . $_SERVER['SCRIPT_NAME'] . '">
<table width="100%" border="0" class="tborder" cellpadding="5" cellspacing="0">
	<tr>
		<td class="thead" align="center">' . $lang->unbaniprequest['title'] . '</td>
	</tr>
	<tr>
		<td class="subheader">' . $lang->unbaniprequest['info'] . '</td>
	</tr>
	<tr>
		<td>
			<fieldset>
				<legend>' . $lang->unbaniprequest['field1'] . '</legend>
				' . $lang->unbaniprequest['field2'] . '<br />
				<input type="text" name="ip" value="' . htmlspecialchars_uni ($userip) . '" size="30" />
			</fieldset>
			<fieldset>
				<legend>' . $lang->unbaniprequest['field3'] . '</legend>
				' . $lang->unbaniprequest['field4'] . '<br />
				<input type="text" name="email" value="' . ($email ? htmlspecialchars_uni ($email) : '') . '" size="30" />
			</fieldset>
			<fieldset>
				<legend>' . $lang->unbaniprequest['field5'] . '</legend>
				' . $lang->unbaniprequest['field6'] . '<br />
				<textarea name="comment" rows="3" cols="60">' . ($comment ? htmlspecialchars_uni ($comment) : '') . '</textarea>
			</fieldset>
			<fieldset>
				<legend>' . $lang->unbaniprequest['field7'] . '</legend>
				<input type="submit" value="' . $lang->unbaniprequest['field8'] . '" /> <input type="submit" value="' . $lang->unbaniprequest['field9'] . '" />
			</fieldset>
		</td>
	</tr>
</table>
</form>
';
  stdfoot ();
?>
