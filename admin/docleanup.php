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

  define ('DC_VERSION', '0.5 by xam');
  define ('SKIP_CRON_JOBS', true);
  define ('RUN_CRONJOBS', true);
  ($db->sql_query ('UPDATE ts_cron SET nextrun = \'0\''));
  $ts_cron_image = '<img src="' . $BASEURL . '/ts_cron.php?rand=' . time () . '&run_cronjobs=true" alt="" width="1" height="1" border="0" />';
  stdhead ('CleanUp');
  //stdmsg ('System Message:' . $ts_cron_image, 'Cleanup operation has been finished. Click <a href=\'#\' onclick=\'javascript:history.go(-1);\'>here</a> to go back.', false, 'success');
  
  $mess = '<div class="container mt-3">
  <div class="alert alert-primary alert-dismissible fade show">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>Primary!</strong>System Message:'.$ts_cron_image.', Cleanup operation has been finished. Click <a href=\'#\' onclick=\'javascript:history.go(-1);\'>here</a> to go back
  </div>
</div>';

  echo $mess;
  
  
  stdfoot ();
?>
