<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['uploads'] = array
(

// ── Common ──
'err_login_required'    => "Чтобы загружать файлы, нужно войти на сайт.",
'err_csrf'              => "Ошибка проверки безопасности. Обновите страницу и попробуйте ещё раз.",
'err_no_file'           => "Файл не был загружен.",
'err_not_image'         => "Файл повреждён или не является картинкой.",
'err_save_failed'       => "Не удалось сохранить файл.",
'err_save_failed_code'  => "Не удалось сохранить файл (код {1}).",
'err_file_too_large'    => "Файл слишком большой (максимум {1}).",
'lbl_size_b'            => "{1} Б",
'lbl_size_kb'           => "{1} КБ",
'lbl_size_mb'           => "{1} МБ",
'lbl_size_gb'           => "{1} ГБ",

// ── Attachment uploader widget ──
'lbl_dropzone'          => "Перетащите файлы сюда",
'lbl_dropzone_or'       => "или",
'lbl_browse'            => "выберите на компьютере",
'hint_dropzone'         => "Картинки, видео, аудио, документы, архивы, субтитры · видео до {1}, аудио до {2}, картинки до {3}, остальные файлы до {4}",
'tip_remove'            => "Убрать",

// ── JS: attachment uploader (comment_attachments.js) ──
'js_upload_failed'      => "Не удалось загрузить",
'js_network'            => "Ошибка сети",
'js_remove'             => "Убрать",
'js_delete_title'       => "Удалить вложение?",
'js_delete_confirm_title' => "Подтверждение удаления",
'js_warning_label'      => "Внимание:",
'js_delete_warning'     => "Отменить это действие нельзя!",
'js_btn_delete'         => "Да, удалить",
'js_btn_cancel'         => "Отмена",
'js_aria_close'         => "Закрыть",
'js_delete_failed'      => "Не удалось удалить вложение.",
'js_size_b'             => "{1} Б",
'js_size_kb'            => "{1} КБ",
'js_size_mb'            => "{1} МБ",

// ── Comment attachments ──
'err_invalid_id'        => "Неверный ID вложения.",
'err_not_found'         => "Вложение не найдено.",
'err_no_permission'     => "У вас нет прав удалить это вложение.",
'err_limit'             => "Достигнут лимит вложений: максимум {1}.",
'err_upload_code'       => "Ошибка загрузки (код {1}).",
'err_file_too_large_cat'=> "Файл слишком большой: для {2} максимум {1}.",
'err_quota'             => "Нельзя прикрепить файл: исчерпана ваша квота на вложения ({1}).",
'err_mime'              => "Этот тип файла не разрешён: {1}",
'err_ext'               => "Это расширение не разрешено: {1}",
'opt_cat_video'         => "видео",
'opt_cat_audio'         => "аудио",
'opt_cat_image'         => "картинок",
'opt_cat_other'         => "остальных файлов",

// ── Editor images ──
'err_unknown_type'      => "Неизвестный тип загрузки.",
'err_img_type'          => "Недопустимый тип файла: {1}",
'err_img_mismatch'      => "Тип картинки не совпадает с заголовком файла — файл повреждён или подделан.",
'err_img_ext'           => "Недопустимое расширение файла.",
'err_multi_target'      => "Указано больше одной цели для вложения.",
'err_comment_not_found' => "Комментарий не найден.",
'err_comment_perm'      => "У вас нет прав на этот комментарий.",
'err_torrent_not_found' => "Раздача не найдена.",
'err_news_not_found'    => "Новость не найдена.",
'err_mkdir'             => "Не удалось создать папку для загрузок.",
'err_db'                => "Ошибка записи в базу данных.",

// ── PHP upload errors ──
'err_php_size'          => "Файл превышает допустимый размер.",
'err_php_partial'       => "Файл загрузился не полностью.",
'err_php_no_file'       => "Файл не выбран.",
'err_php_no_tmp'        => "На сервере нет временной папки.",
'err_php_cant_write'    => "Не удалось записать файл на диск.",
'err_php_extension'     => "Загрузку остановило расширение PHP.",
'err_php_unknown'       => "Неизвестная ошибка загрузки.",

);
