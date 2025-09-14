<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function goback ()
  {
    global $_this_script_;
    $msg = 'Click <a href=' . $_this_script_ . '>here</a> to go back!';
    return $msg;
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('UL_VERSION', 'by xam v.0.8');
  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : ''));
  if ($action == 'delete')
  {
    $id = 0 + $_GET['id'];
    $sure = 0 + $_GET['sure'];
    if (!is_valid_id ($id))
    {
      stderr ('Error', 'Invalid ID!');
    }

    if (!$sure)
    {
      stderr ('Delete Request', 'Sanity check: You are about to delete a request. Click
<a href=' . $_this_script_ . ('' . '&action=delete&id=' . $id . '&sure=1>here</a> if you are sure. ') . goback (), false);
    }
    else
    {
      ($db->sql_query ('DELETE FROM unbanrequests WHERE id = ' . $db->sqlesc ($id)) OR stderr ('Delete Request', '' . 'Unable to delete: ' . $id . ' ' . goback (), false));
    }
  }

  stdhead ('View Unban Requests ' . UL_VERSION . ' - SHOWLIST');
  _form_header_open_ ('View Unban Requests ' . UL_VERSION);
  echo '<table border=0 class=tborder cellspacing=0 cellpadding=10 width=100%>';
  $page = (int)$_GET['page'];
  $perpage = $ts_perpage;
  $countrows = number_format (tsrowcount ('id', 'unbanrequests'));
  //list ($pagertop, $pagerbottom, $limit) = pager ($perpage, $countrows, $_this_script_ . '&');
  ($res = $db->sql_query ('SELECT u.*, l.id as loginaid FROM unbanrequests u LEFT JOIN loginattempts l on (u.ip=l.ip OR u.realip=l.ip) ORDER BY u.added DESC ' . $limit));
  if ($db->num_rows ($res) == 0)
  {
    echo '<tr><td colspan=7><b>Nothing found</b></td></tr>';
  }
  else
  {
    echo '<tr><td class=subheader align=center>ID</td><td class=subheader align=left>IP</td><td class=subheader align=left>REAL IP</td><td class=subheader align=left>EMAIL</td><td class=subheader align=left>COMMENT</td><td class=subheader align=left>ADDED</td><td class=subheader align=left>ACTION</d></tr>';
    while ($arr = mysqli_fetch_array ($res))
    {
      echo '<tr><td align=center><span id="show_id' . $arr['id'] . '">' . $arr['id'] . '</span></td><td align=left>' . htmlspecialchars_uni ($arr['ip']) . '</td><td align=left>' . htmlspecialchars_uni ($arr['realip']) . '</td><td align=left>' . htmlspecialchars_uni ($arr['email']) . '</td><td align=left>' . htmlspecialchars_uni ($arr['comment']) . '</td><td align=left>' . my_datee ($dateformat, $arr['added']) . ' ' . my_datee ($timeformat, $arr['added']) . '</td><td>' . ($arr['loginaid'] ? ' [<a href=' . $_this_script_no_act . '?act=maxlogin&action=edit&id=' . $arr['loginaid'] . '&return=yes>E</a>]' : '') . ' [<a href=' . $_this_script_ . '&action=delete&id=' . $arr['id'] . '&return=yes>D</a>] ' . ($arr['loginaid'] ? ' [<a href=' . $_this_script_no_act . '?act=maxlogin&action=delete&id=' . $arr['loginaid'] . '&return=yes>DFLA</a>]' : '') . '</td></tr>';
    }

    echo '<tr><td colspan=7><div class=error>To remove ban click on E button, to delete request click on D button, to delete failed login attempt click on DFLA.<br />No [E] (edit) or [DFLA] (Delete Failed Login Attemp) button? That means the unban request could not found in the database of failed attempts!</div></td></tr>';
    echo '</table>';
    echo $pagerbottom;
  }

  _form_header_close_ ();
  stdfoot ();
?>
