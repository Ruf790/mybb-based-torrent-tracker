<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function show_tutorial_errors ()
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

  require_once 'global.php';
  gzip ();

  loggedinorreturn ();
  $lang->load ('ts_tutorials');
  define ('TST_VERSION', '0.1 ');
  $do = (isset ($_GET['do']) ? $_GET['do'] : (isset ($_POST['do']) ? $_POST['do'] : ''));
  $str = '';
  $Tutorials = '';
  $Title = sprintf ($lang->ts_tutorials['head'], $SITENAME);
  $errors = array ();
  $is_mod = is_mod ($usergroups);
  if (((($do == 'delete' AND $is_mod) AND is_valid_id ($_GET['tid'])) AND $Tid = intval ($_GET['tid'])))
  {
    ($Query = $db->sql_query ('SELECT title, content FROM ts_tutorials WHERE tid = \'' . $Tid . '\'') OR sqlerr (__FILE__, 33));
    if ($db->num_rows ($Query) == 0)
    {
      unset ($do);
      $errors[] = $lang->ts_tutorials['error2'];
    }
    else
    {
      ($db->sql_query ('DELETE FROM ts_tutorials WHERE tid = \'' . $Tid . '\'') OR sqlerr (__FILE__, 41));
      unset ($do);
    }
  }

  if (($do == 'new' AND $is_mod))
  {
    if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
    {
      if ((!empty ($_POST['title']) AND !empty ($_POST['content'])))
      {
        $ETitle = trim ($_POST['title']);
        $EContent = trim ($_POST['content']);
        $EViews = intval ($_POST['views']);
        ($db->sql_query ('INSERT INTO ts_tutorials VALUES (NULL, \'' . $CURUSER['id'] . '\', \'' . time () . '\', ' . $db->sqlesc ($ETitle) . ', ' . $db->sqlesc ($EContent) . ', \'' . $EViews . '\')') OR sqlerr (__FILE__, 55));
        if ($db->affected_rows ())
        {
          $edited = true;
        }
      }
      else
      {
        $errors[] = $lang->global['dontleavefieldsblank'];
      }
    }

    if ($edited)
    {
      unset ($do);
      $do = 'show_tutorial';
      $_GET['tid'] = $db->insert_id ();
    }
    else
    {
      $Tutorials .= '
		<form method="POST" action="' . $_SERVER['SCRIPT_NAME'] . '?do=new">
		<tr>
			<td class="subheader" width="20%" valign="top" align="right">' . $lang->ts_tutorials['title'] . '</td>
			<td valign="top" align="left"><input type="text" name="title" value="' . htmlspecialchars_uni (($ETitle ? $ETitle : '')) . '" size="60" /></td>
		</tr>
		<tr>
			<td class="subheader" width="20%" valign="top" align="right">' . $lang->ts_tutorials['content'] . '</td>
			<td valign="top" align="left"><textarea name="content" cols="100" rows="5">' . htmlspecialchars_uni (($EContent ? $EContent : '')) . '</textarea></td>
		</tr>
		<tr>
			<td class="subheader" width="20%" valign="top" align="right">' . $lang->ts_tutorials['views'] . '</td>
			<td valign="top" align="left"><input type="text" name="views" value="' . htmlspecialchars_uni (($EViews ? $EViews : 0)) . '" size="10" /></td>
		</tr>
		<tr>
			<td align="center" colspan="2"><input type="submit" value="' . $lang->ts_tutorials['save'] . '" /> <input type="reset" value="' . $lang->ts_tutorials['reset'] . '" /></td>
		</tr>
		</form>
		';
    }
  }

  if (((($do == 'edit' AND $is_mod) AND is_valid_id ($_GET['tid'])) AND $Tid = intval ($_GET['tid'])))
  {
    ($Query = $db->sql_query ('SELECT title, content, views FROM ts_tutorials WHERE tid = \'' . $Tid . '\'') OR sqlerr (__FILE__, 98));
    if ($db->num_rows ($Query) == 0)
    {
      unset ($do);
      $errors[] = $lang->ts_tutorials['error2'];
    }
    else
    {
      $Tut = mysqli_fetch_assoc ($Query);
      if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
      {
        $edited = false;
        if ((!empty ($_POST['title']) AND !empty ($_POST['content'])))
        {
          $ETitle = trim ($_POST['title']);
          $EContent = trim ($_POST['content']);
          $EViews = intval ($_POST['views']);
          ($db->sql_query ('UPDATE ts_tutorials SET title = ' . $db->sqlesc ($ETitle) . ', content = ' . $db->sqlesc ($EContent) . ', views = ' . $db->sqlesc ($EViews) . ' WHERE tid = \'' . $Tid . '\'') OR sqlerr (__FILE__, 115));
          if ($db->affected_rows ())
          {
            $edited = true;
          }
        }
        else
        {
          $errors[] = $lang->global['dontleavefieldsblank'];
        }
      }

      if ($edited)
      {
        unset ($do);
        $do = 'show_tutorial';
        $_GET['tid'] = $Tid;
      }
      else
      {
        $Tutorials .= '
			<form method="POST" action="' . $_SERVER['SCRIPT_NAME'] . '?do=edit&amp;tid=' . $Tid . '">
			<tr>
				<td class="subheader" width="20%" valign="top" align="right">' . $lang->ts_tutorials['title'] . '</td>
				<td valign="top" align="left"><input type="text" name="title" value="' . htmlspecialchars_uni (($ETitle ? $ETitle : $Tut['title'])) . '" size="60" /></td>
			</tr>
			<tr>
				<td class="subheader" width="20%" valign="top" align="right">' . $lang->ts_tutorials['content'] . '</td>
				<td valign="top" align="left"><textarea name="content" cols="100" rows="5">' . htmlspecialchars_uni (($EContent ? $EContent : $Tut['content'])) . '</textarea></td>
			</tr>
			<tr>
				<td class="subheader" width="20%" valign="top" align="right">' . $lang->ts_tutorials['views'] . '</td>
				<td valign="top" align="left"><input type="text" name="views" value="' . htmlspecialchars_uni (($EViews ? $EViews : $Tut['views'])) . '" size="10" /></td>
			</tr>
			<tr>
				<td align="center" colspan="2"><input type="submit" value="' . $lang->ts_tutorials['save'] . '" /> <input type="reset" value="' . $lang->ts_tutorials['reset'] . '" /></td>
			</tr>
			</form>
			';
      }
    }
  }

  if ((($do == 'show_tutorial' AND is_valid_id ($_GET['tid'])) AND $Tid = intval ($_GET['tid'])))
  {
    ($Query = $db->sql_query ('SELECT t.*, u.username, g.namestyle FROM ts_tutorials t LEFT JOIN users u ON (t.uid=u.id) LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE t.tid = \'' . $Tid . '\'') OR sqlerr (__FILE__, 159));
    if ($db->num_rows ($Query) == 0)
    {
      unset ($do);
      $errors[] = $lang->ts_tutorials['error2'];
    }
    else
    {
      ($db->sql_query ('UPDATE ts_tutorials SET views = views + 1 WHERE tid = \'' . $Tid . '\'') OR sqlerr (__FILE__, 167));
      $Tut = mysqli_fetch_assoc ($Query);
      $DateContent = sprintf ($lang->ts_tutorials['by'], '<b>' . my_datee ($dateformat, $Tut['date']) . ' ' . my_datee ($timeformat, $Tut['date']) . '</b>', '<a href="' . ts_seo ($Tut['uid'], $Tut['username']) . '">' . get_user_color ($Tut['username'], $Tut['namestyle']) . '</a>');
      $Tutorials .= '
		<tr>
			<td>
				<table width="100%" border="0" cellpadding="5" cellspacing="0">
					<tr>
						<td width="100%" align="left" valign="top">
							<span style="float: right;">' . $DateContent . '</span>
							<h1>' . htmlspecialchars_uni ($Tut['title']) . '</h1>
							<hr>
							' . format_comment ($Tut['content']) . '
						</td>
					</tr>
				</table>
			</td>
		</tr>
		';
    }
  }

  if (!$do)
  {
    ($Query = $db->sql_query ('SELECT tid FROM ts_tutorials') OR sqlerr (__FILE__, 191));
    $Count = $db->num_rows ($Query);
    list ($pagertop, $pagerbottom, $limit) = pager (10, $Count, $_SERVER['SCRIPT_NAME'] . '?');
    $Tutorials .= '
	<tr>
		<td class="subheader" width="50%">' . $lang->ts_tutorials['title'] . '</td>
		<td class="subheader" width="15%">' . $lang->ts_tutorials['sender'] . '</td>
		<td class="subheader" width="15%">' . $lang->ts_tutorials['date'] . '</td>
		<td class="subheader" width="10%" align="center">' . $lang->ts_tutorials['views'] . '</td>
	</tr>
	';
    ($Query = $db->sql_query ('SELECT t.*, u.username, g.namestyle FROM ts_tutorials t LEFT JOIN users u ON (t.uid=u.id) LEFT JOIN usergroups g ON (u.usergroup=g.gid) ORDER BY t.date ' . $limit) OR sqlerr (__FILE__, 202));
    if (0 < $db->num_rows ($Query))
    {
      while ($Tut = mysqli_fetch_assoc ($Query))
      {
        $Tutorials .= '
			<tr>
				<td width="50%">' . ($is_mod ? '<span style="float: right;">[<a href="' . $_SERVER['SCRIPT_NAME'] . '?do=delete&amp;tid=' . $Tut['tid'] . '" alt="' . $lang->ts_tutorials['delete'] . '" title="' . $lang->ts_tutorials['delete'] . '" onclick="return confirm_delete();">' . $lang->ts_tutorials['delete'] . '</a>] [<a href="' . $_SERVER['SCRIPT_NAME'] . '?do=edit&amp;tid=' . $Tut['tid'] . '" alt="' . $lang->ts_tutorials['edit'] . '" title="' . $lang->ts_tutorials['edit'] . '">' . $lang->ts_tutorials['edit'] . '</a>]</span>' : '') . '<a href="' . $_SERVER['SCRIPT_NAME'] . '?do=show_tutorial&amp;tid=' . $Tut['tid'] . '">' . htmlspecialchars_uni ($Tut['title']) . '</a></td>
				<td width="15%"><a href="' . ts_seo ($Tut['uid'], $Tut['username']) . '">' . get_user_color ($Tut['username'], $Tut['namestyle']) . '</a></td>
				<td width="15%">' . my_datee ($dateformat, $Tut['date']) . ' ' . my_datee ($timeformat, $Tut['date']) . '</td>
				<td width="10%" align="center">' . ts_nf ($Tut['views']) . '</td>
			</tr>';
      }
    }
    else
    {
      $Tutorials .= '
		<tr>
			<td colspan="4">' . $lang->ts_tutorials['error'] . '</td>
		</tr>';
    }
  }

  $newtutorial = '<p style="float: right;"><input type="button" value="' . $lang->ts_tutorials['new'] . '" onclick="jumpto(\'' . $_SERVER['SCRIPT_NAME'] . '?do=new\'); return false;" /></p>';
  $backbutton = '<p style="float: right;"><input type="button" value="' . $lang->ts_tutorials['back'] . '" onclick="jumpto(\'' . $_SERVER['SCRIPT_NAME'] . '?\'); return false;" /></p>';
  $str .= ($is_mod ? '
	<script type="text/javascript">
		function confirm_delete()
		{
			if (confirm("' . $lang->ts_tutorials['sure'] . '"))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
	</script>
	' : '') . '
	<table class="main" border="1" cellspacing="0" cellpadding="5" width="100%">
		<tr>
			<td class="thead" colspan="4">' . ts_collapse ('title') . $Title . '</td>
		</tr>
		' . ts_collapse ('title', 2) . '
		' . $Tutorials . '
		</tbody>
	</table>
	';
  stdhead ($Title, true, 'collapse');
  show_tutorial_errors ();
  echo $pagertop . ((!$do AND $is_mod) ? $newtutorial : $backbutton) . $str . $pagerbottom;
  stdfoot ();
?>
