<?php
declare(strict_types=1);


  if (!defined ('STAFF_PANEL'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }
  

  if (empty($CURUSER['id']) || !is_mod($usergroups))
  {
    http_response_code(403);
    exit('<div class="alert alert-danger">Error! You do not have permission to access this page.</div>');
  }

  define ('DC_VERSION', '0.6');

  // ── Экран подтверждения (GET, ничего не меняет) ─────────────────────────
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm']))
  {
    stdhead('CleanUp');
    echo '
<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Sanity Check!</strong>
        </div>
        <div class="card-body">
            <p>This will force ALL scheduled cron jobs to run immediately (regardless of their normal schedule), including any that delete or modify data.</p>
            <form method="post">
                <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger"><i class="bi bi-play-fill me-1"></i> Force Run All Cron Jobs Now</button>
                <a href="javascript:history.go(-1);" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>';
    stdfoot();
    exit;
  }

  // ── Реальный запуск - только POST с валидным CSRF-токеном ───────────────
  if (!verify_post_check($mybb->get_input('my_post_key')))
  {
    http_response_code(403);
    exit('<div class="alert alert-danger">Invalid security token. Please try again from the page.</div>');
  }

  define ('RUN_CRONJOBS', true);

  // WHERE cronid > 0 добавлен не только для ясности запроса, но и потому,
  // что MySQL в режиме sql_safe_updates отклоняет UPDATE без WHERE по
  // ключевой колонке или LIMIT — раньше запрос без WHERE мог тихо
  // проваливаться на серверах с этой настройкой, а результат вообще
  // не проверялся.
  $reset_result = $db->sql_query_prepared('UPDATE cron SET nextrun = ? WHERE cronid > 0', [0]);

  if (!$reset_result) {
      http_response_code(500);
      exit('<div class="alert alert-danger">Error: Failed to reset cron schedule (nextrun update failed). '
          . 'The database may have safe-update mode enabled, or another error occurred — check the server error log.</div>');
  }

  $ts_cron_image = '<img src="' . $BASEURL . '/cron.php?rand=' . time () . '&run_cronjobs=true" alt="" width="1" height="1" border="0" />';
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