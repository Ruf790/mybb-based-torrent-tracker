<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// editpost.php
$language['editpost'] = array
(


'remove_attachment' => "Удалить",
'update_attachment' => "Обновить вложение",
'add_attachment' => "Добавить вложение",
'new_attachment' => "Новое вложение:",

// ── Page / navigation ──
'nav_edit_post' => 'Редактирование сообщения',
'pane_edit_post' => 'Редактирование этого сообщения',
'title_post_not_found' => 'Сообщение не найдено',
'title_access_denied' => 'Доступ запрещён',

// ── Form: panes / links ──
'pane_post_options' => 'Параметры сообщения',
'pane_attachments' => 'Вложения',
'pane_mod_options' => 'Опции модератора',
'pane_poll' => 'Опрос',

// ── Form: fields / buttons ──
'ph_edit_reason' => 'Причина редактирования (необязательно)',
'btn_preview_post' => 'Предпросмотр',
'btn_update_post' => 'Сохранить изменения',

// ── Moderator options ──
'lbl_close_thread' => 'Закрыть тему',
'lbl_stick_thread' => 'Прикрепить тему',

// ── Poll ──
'tip_poll_desc' => 'При желании можно прикрепить к теме опрос.',
'lbl_poll_check' => 'Создать опрос',
'lbl_poll_num_options' => 'Количество вариантов:',
'hint_poll_max_options' => '(максимум: {1})',

// ── Attachments ──
'lbl_new_attachment' => 'Новое вложение:',
'btn_add_attachment' => 'Добавить вложение',
'btn_update_attachment' => 'Обновить вложения',
'btn_remove_attachment' => 'Удалить',
'btn_approve_attachment' => 'Одобрить',
'btn_unapprove_attachment' => 'Снять одобрение',
'btn_insert_into_post' => 'Вставить в сообщение',
'tip_attach_quota' => 'Ваша квота на вложения: {1}.',
'tip_attach_usage' => 'Сейчас используется: <strong>{1}</strong>.',
'lbl_view_attachments' => '[Мои вложения]',
'lbl_unlimited' => 'без ограничений',

// ── Delete post (card + modal) ──
'tip_delete_note' => '<b>Внимание:</b> если это первое сообщение в теме, его удаление приведёт к удалению всей темы.',
'btn_delete_now' => 'Удалить',
'modal_delete_title' => 'Удаление сообщения',
'modal_delete_confirm' => 'Вы уверены, что хотите удалить это сообщение?',
'modal_delete_warning' => 'Это действие нельзя отменить.',
'lbl_pid' => 'ID сообщения: {1}',
'modal_deleting_sr' => 'Удаление...',
'modal_deleting' => 'Удаление сообщения...',
'btn_cancel' => 'Отмена',
'btn_delete_post' => 'Удалить сообщение',

// ── Flash messages / errors ──
'flash_invalid_post' => 'Извините, вы перешли по неверной ссылке. Убедитесь, что указанное сообщение существует, и повторите попытку.',
'flash_thread_closed' => 'Вы не можете редактировать сообщения в этой теме: она закрыта модератором.',
'flash_already_deleted' => 'Это сообщение уже удалено.',
'flash_empty_post_input' => 'Запрос отклонён: данные не были получены. Обычно это происходит, когда общий размер загружаемых файлов превышает лимит сервера.',
'flash_nodelete' => 'Сообщение не удалено: удаление не было подтверждено.',
'flash_post_edited' => 'Спасибо, сообщение отредактировано.<br />Сейчас вы будете перенаправлены в тему.',
'flash_post_edited_poll' => 'Спасибо, сообщение отредактировано.<br />Так как вы выбрали создание опроса, сейчас вы будете перенаправлены на страницу создания опроса.',
'flash_thread_moderation' => 'Администрация включила премодерацию правок тем. Сейчас вы будете перенаправлены на главную страницу форума.',
'flash_post_moderation' => 'Администрация включила премодерацию правок сообщений. Сейчас вы будете перенаправлены в тему.',
'flash_thread_deleted' => 'Спасибо, тема удалена.<br />Сейчас вы будете перенаправлены в раздел.',
'flash_post_deleted' => 'Спасибо, сообщение удалено.<br />Сейчас вы будете перенаправлены в тему.',

// ── JS: attachments (consumed by post.js via lang.*) ──
'js_add_attachment' => 'Добавить вложение',
'js_update_attachment' => 'Обновить вложения',
'js_update_confirm' => 'Следующие файлы уже прикреплены и будут обновлены / заменены вновь выбранными. {1} Вы уверены?',
'js_attachment_missing' => 'Выберите один или несколько файлов, прежде чем прикреплять.',
'js_attachment_too_many_files' => 'За один раз можно загрузить не более {1} файлов.',
'js_attachment_too_big_upload' => 'За один раз можно загрузить не более {1} МБ.',
'js_attachment_max_allowed_files' => 'К этому сообщению можно прикрепить ещё файлов: {1}.',
'js_error_maxattachpost' => 'Не удалось прикрепить файл: достигнуто максимальное число вложений на одно сообщение ({1}).',
'js_drop_files' => 'Нажмите или перетащите файлы сюда для загрузки...',
'js_upload_initiate' => 'Отпустите, чтобы начать загрузку...',

// ── JS: delete post (delete_post_editpost.js) ──
'js_deleting' => 'Удаление...',
'js_thread_deleted' => 'Тема успешно удалена',
'js_post_deleted' => 'Сообщение успешно удалено',
'js_delete_unexpected' => 'Непредвиденный ответ сервера при удалении сообщения',
'js_delete_error' => 'Ошибка при удалении сообщения',





);

?>