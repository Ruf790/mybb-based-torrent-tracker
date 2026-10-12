<?php
declare(strict_types=1);


  if (!defined ('STAFF_PANEL'))
  {
    // Ланг здесь ещё недоступен (файл открыт напрямую) — сообщение остаётся на английском
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  $lang->load('docleanup');

  if (!function_exists('ags_fmt'))
  {
    /**
     * Подстановка {1}, {2}… (и %1$s, %2$s… — в них $lang->load() превращает {N}).
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
      $map = [];
      foreach ($args as $i => $arg)
      {
        $n = $i + 1;
        $map['{' . $n . '}']  = (string)$arg;
        $map['%' . $n . '$s'] = (string)$arg;
      }
      return strtr($str, $map);
    }
  }

  if (empty($CURUSER['id']) || !is_mod($usergroups))
  {
    http_response_code(403);
    exit('<div class="alert alert-danger">' . htmlspecialchars($lang->docleanup['err_no_permission']) . '</div>');
  }

  define ('DC_VERSION', '0.6');

  // ── Экран подтверждения (GET, ничего не меняет) ─────────────────────────
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm']))
  {
    stdhead($lang->docleanup['page_title']);
    echo '
<div class="container mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>' . htmlspecialchars($lang->docleanup['sec_sanity_check']) . '</strong>
        </div>
        <div class="card-body">
            <p>' . htmlspecialchars($lang->docleanup['txt_confirm_warning']) . '</p>
            <form method="post">
                <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger"><i class="bi bi-play-fill me-1"></i> ' . htmlspecialchars($lang->docleanup['btn_run_all']) . '</button>
                <a href="javascript:history.go(-1);" class="btn btn-outline-secondary">' . htmlspecialchars($lang->docleanup['btn_cancel']) . '</a>
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
    exit('<div class="alert alert-danger">' . htmlspecialchars($lang->docleanup['err_invalid_token']) . '</div>');
  }

  define ('RUN_CRONJOBS', true);

  $reset_result = $db->sql_query_prepared('UPDATE cron SET nextrun = ? WHERE cronid > 0', [0]);

  if (!$reset_result) {
      http_response_code(500);
      exit('<div class="alert alert-danger">' . htmlspecialchars($lang->docleanup['err_cron_reset_failed']) . '</div>');
  }

  $ts_cron_image = '<img src="' . $BASEURL . '/cron.php?rand=' . time () . '&run_cronjobs=true" alt="" width="1" height="1" border="0" />';
  stdhead ($lang->docleanup['page_title']);

  $back_link = '<a href="#" onclick="javascript:history.go(-1);">' . htmlspecialchars($lang->docleanup['lnk_here']) . '</a>';

$mess = '

<link href="'.$BASEURL.'/include/templates/default/style/messagess.css" rel="stylesheet">

<div class="container mt-3">
<div class="card error-card4 fade show" id="system_notice">
  <div class="card-header4">
    <i class="bi bi-info-circle-fill error-icon4"></i>
    <div><h2 class="mb-0">' . htmlspecialchars($lang->docleanup['sec_system_message']) . '</h2></div>

    <div class="float-end ms-auto">
      <a href="#" title="' . htmlspecialchars($lang->docleanup['lbl_close']) . '" aria-label="' . htmlspecialchars($lang->docleanup['lbl_close']) . '" data-bs-dismiss="alert">
        <i class="btn-close"></i>
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="alert4 alert-primary" role="alert">
      <strong>' . htmlspecialchars($lang->docleanup['lbl_done']) . '</strong>
      ' . ags_fmt(htmlspecialchars($lang->docleanup['msg_cleanup_finished']), $ts_cron_image) . '
      ' . ags_fmt(htmlspecialchars($lang->docleanup['msg_go_back']), $back_link) . '
    </div>
  </div>
</div>
</div>

';

  echo $mess;

  stdfoot ();
