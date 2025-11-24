<?
declare(strict_types=1);


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
  
  

$mess = '

<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/messagess.css" rel="stylesheet">

<div class="container mt-3">
<div class="card error-card4 fade show" id="system_notice">
  <div class="card-header4">
    <i class="bi bi-info-circle-fill error-icon4"></i>
    <div><h2 class="mb-0">System Message</h2></div>

    <div class="float-end ms-auto">
      <a href="#" title="Close" data-bs-dismiss="alert">
        <i class="btn-close"></i>
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="alert4 alert-primary" role="alert">
      <strong>Primary!</strong>
      System Message: '.$ts_cron_image.', Cleanup operation has been finished.
      Click <a href="#" onclick="javascript:history.go(-1);">here</a> to go back.
    </div>
  </div>
</div>
</div>

';








  echo $mess;
  
  
  stdfoot ();
?>
