<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function log_user_deletion ($why)
  {
    write_log ($why);
  }

  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  if (!function_exists ('write_log'))
  {
    function write_log ($text)
    {
      global $db;
	  $text = $db->sqlesc($text);
      $added = TIMENOW;
      (sql_query ('' . 'INSERT INTO sitelog (added, txt) VALUES(' . $added . ', ' . $text . ')'));
    }
  }

?>
