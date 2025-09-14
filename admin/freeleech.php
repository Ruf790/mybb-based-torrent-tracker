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

  define ('FL_VERSION', '0.2 by xam');
  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : 'main'));
  if ($action == 'setallfree')
  {
    $db->sql_query ('UPDATE torrents SET free = \'yes\' WHERE free = \'no\'');
    write_log ('' . 'All torrents set to Free by ' . $CURUSER['username']);
    stderr ('Success', 'All torrents have been set free..');
    return 1;
  }

  if ($action == 'setallnormal')
  {
    $db->sql_query ('UPDATE torrents SET free = \'no\' WHERE free = \'yes\'');
    write_log ('' . 'All torrents set to Normal by ' . $CURUSER['username']);
    stderr ('Success', 'All torrents have been set normal..');
    return 1;
  }

  if ($action == 'main')
  {
    stderr ('Select action </br></br>Click <a href=' . $_this_script_ . '&action=setallfree>here</a> to set all torrents free.. <br /><br /> Click <a href=' . $_this_script_ . '&action=setallnormal>here</a> to set all torrents normal..', false);
  }

?>
