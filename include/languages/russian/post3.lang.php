<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// shared: scripts/post.js + newthread.php / newreply.php / editpost.php
$language['post3'] = array
(

// ── Attachments: shared labels / buttons (newthread, newreply, editpost) ──
'lbl_new_attachment' => 'Новое вложение:',
'btn_add_attachment' => 'Добавить вложение',
'btn_update_attachment' => 'Обновить вложения',
'btn_remove_attachment' => 'Удалить',

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

// ── JS: attachment modal and toasts (post.js) ──
'js_removeattach_confirm' => 'Вы уверены, что хотите удалить это вложение?',
'js_attach_modal_title' => 'Подтверждение удаления',
'js_attach_modal_close' => 'Закрыть',
'js_attach_modal_heading' => 'Удалить вложение?',
'js_attach_modal_warning_label' => 'Внимание:',
'js_attach_modal_warning' => 'Это действие нельзя отменить!',
'js_attach_modal_cancel' => 'Отмена',
'js_attach_modal_confirm' => 'Да, удалить',
'js_attach_preview_alt' => 'Предпросмотр',
'js_attach_file_label' => 'файл',
'js_attach_removed' => 'Вложение успешно удалено',
'js_attach_remove_error' => 'Ошибка при удалении вложения',
'js_files_uploaded' => 'Файлы успешно загружены',
'js_files_upload_error' => 'Ошибка при загрузке файлов',
'js_file_input_missing' => 'Поле выбора файлов не найдено',

);

?>