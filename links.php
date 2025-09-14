<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  define ('L_VERSION', '0.2');
  require_once 'global.php';
  gzip ();

  include_once INC_PATH . '/functions_security.php';
  $lang->load ('links');
  stdhead ($lang->links['head']);
  echo $lang->links['info'];
  stdfoot ();
?>
