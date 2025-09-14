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

  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : 'main'));
  if ($action == 'setalldouble')
  {
    $db->sql_query ('UPDATE torrents SET doubleupload = \'yes\' WHERE doubleupload = \'no\'');
    stderr ('Success, All torrents have been set doubleupload..');
    return 1;
  }

  if ($action == 'setallnormal')
  {
    $db->sql_query ('UPDATE torrents SET doubleupload = \'no\' WHERE doubleupload = \'yes\'');
    stderr ('Success, All torrents have been set normal..');
    return 1;
  }

  if ($action == 'main')
  {
    stderr ('Select action, Click <a href=' . $_this_script_ . '&action=setalldouble>here</a> to set all torrents doubleupload.. <br /><br /> Click <a href=' . $_this_script_ . '&action=setallnormal>here</a> to set all torrents normal..', false);
  }

?>
