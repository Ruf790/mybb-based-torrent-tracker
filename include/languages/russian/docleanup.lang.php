<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['docleanup'] = array
(
    // ── Page ──
    'page_title'            => 'Очистка',

    // ── Confirmation screen ──
    'sec_sanity_check'      => 'Вы уверены?',
    'txt_confirm_warning'   => 'Все задачи cron будут запущены немедленно, вне своего расписания — в том числе те, что удаляют или изменяют данные.',
    'btn_run_all'           => 'Запустить все задачи cron сейчас',
    'btn_cancel'            => 'Отмена',

    // ── Result message ──
    'sec_system_message'    => 'Системное сообщение',
    'lbl_close'             => 'Закрыть',
    'lbl_done'              => 'Готово!',
    'msg_cleanup_finished'  => 'Системное сообщение: {1} очистка завершена.',
    'msg_go_back'           => 'Нажмите {1}, чтобы вернуться назад.',
    'lnk_here'              => 'здесь',

    // ── Errors ──
    'err_no_permission'     => 'Ошибка! У вас нет доступа к этой странице.',
    'err_invalid_token'     => 'Неверный токен безопасности. Вернитесь на страницу и попробуйте ещё раз.',
    'err_cron_reset_failed' => 'Ошибка: не удалось сбросить расписание cron (не обновился nextrun). Возможно, в базе включён режим safe-update или произошла другая ошибка — проверьте журнал ошибок сервера.',
);
?>
