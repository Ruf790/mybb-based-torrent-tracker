<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['news'] = array
(

// ── Page header ──
'page_title'            => "Управление новостями",
'pane_title'            => "Новости сайта",
'pane_subtitle'         => "Публикуйте объявления и поддерживайте архив новостей в актуальном состоянии.",
'lbl_head_chip'         => "Менеджер новостей",

// ── KPI tiles ──
'lbl_kpi_total'         => "Всего новостей",
'lbl_kpi_month'         => "Опубликовано в этом месяце",
'lbl_kpi_authors'       => "Авторов",
'lbl_kpi_latest'        => "Последняя новость",

// ── Create form ──
'sec_create'            => "Новая новость",
'hint_create'           => "Расскажите сообществу о важных изменениях и событиях",
'lbl_title'             => "Заголовок",
'ph_title'              => "Придумайте цепляющий заголовок…",
'lbl_content'           => "Текст новости",
'ph_content'            => "Напишите текст новости…",
'lbl_char_count'        => "{1} / {2} символов",
'btn_publish'           => "Опубликовать",
'btn_clear'             => "Очистить",

// ── Archive ──
'sec_archive'           => "Архив новостей",
'lbl_by'                => "автор: {1}",
'lbl_unknown_user'      => "Неизвестный пользователь",
'lbl_news_id'           => "ID новости: #{1}",
'btn_edit'              => "Изменить",
'btn_delete'            => "Удалить",
'tip_edit'              => "Редактировать новость",
'tip_delete'            => "Удалить новость",
'sec_empty'             => "Новостей пока нет",
'hint_empty'            => "Опубликуйте первую новость!",
'lbl_load_error'        => "Ошибка загрузки новостей:",

// ── Edit modal ──
'sec_edit'              => "Редактирование новости",
'lbl_edit_title'        => "Заголовок",
'ph_edit_title'         => "Введите заголовок новости",
'lbl_edit_content'      => "Текст",
'lbl_preview'           => "Предпросмотр",
'hint_preview'          => "Здесь появится предпросмотр по мере набора…",
'btn_cancel'            => "Отмена",
'btn_save'              => "Сохранить",
'aria_close'            => "Закрыть",

// ── BBCode toolbar (edit modal) ──
'tip_bb_bold'           => "Жирный",
'tip_bb_italic'         => "Курсив",
'tip_bb_underline'      => "Подчёркнутый",
'tip_bb_strike'         => "Зачёркнутый",
'tip_bb_left'           => "По левому краю",
'tip_bb_center'         => "По центру",
'tip_bb_right'          => "По правому краю",
'tip_bb_red'            => "Красный текст",
'tip_bb_blue'           => "Синий текст",
'tip_bb_green'          => "Зелёный текст",
'tip_bb_url'            => "Ссылка",
'tip_bb_img'            => "Картинка",
'tip_bb_video'          => "Видео",
'tip_bb_youtube'        => "YouTube",
'tip_bb_quote'          => "Цитата",
'tip_bb_code'           => "Код",
'tip_bb_spoiler'        => "Спойлер",
'tip_bb_ul'             => "Маркированный список",
'tip_bb_ol'             => "Нумерованный список",
'tip_bb_li'             => "Пункт списка",
'tip_bb_torrent'        => "Вставить карточку раздачи",
'lbl_bb_torrent'        => "Раздача",
'lbl_torrent_id'        => "ID или ссылка на раздачу",
'ph_torrent_id'         => "например 17 или вставьте ссылку на раздачу",
'btn_insert'            => "Вставить",

// ── AJAX errors ──
'err_access_denied'     => "Доступ запрещён.",
'err_method'            => "Метод не поддерживается.",
'err_csrf'              => "Ошибка проверки безопасности. Обновите страницу и попробуйте ещё раз.",
'err_invalid_id'        => "Неверный ID новости.",
'err_not_found'         => "Новость не найдена.",
'err_title_empty'       => "Заголовок не может быть пустым.",
'err_content_empty'     => "Текст новости не может быть пустым.",
'err_title_long'        => "Слишком длинный заголовок (максимум {1} символов).",
'err_content_long'      => "Слишком длинный текст (максимум {1} символов).",
'err_invalid_user'      => "Неверный ID пользователя.",
'err_update_failed'     => "Не удалось обновить новость.",
'err_create_failed'     => "Не удалось создать новость.",
'err_invalid_action'    => "Неизвестное действие.",
'err_internal'          => "Внутренняя ошибка сервера.",

// ── AJAX success ──
'flash_deleted'         => "Новость удалена.",
'flash_updated'         => "Новость обновлена.",
'flash_added'           => "Новость добавлена.",

// ── JS ──
'js_publishing'         => "Публикуем…",
'js_saving'             => "Сохраняем…",
'js_published'          => "Новость опубликована!",
'js_updated'            => "Новость обновлена!",
'js_deleted'            => "Новость удалена!",
'js_add_failed'         => "Не удалось опубликовать новость.",
'js_edit_failed'        => "Не удалось сохранить изменения.",
'js_delete_failed'      => "Не удалось удалить.",
'js_network'            => "Ошибка сети. Попробуйте ещё раз.",
'js_invalid_item'       => "Неверная новость.",
'js_title_empty'        => "Заголовок не может быть пустым.",
'js_content_empty'      => "Текст новости не может быть пустым.",
'js_delete_title'       => "Удалить эту новость?",
'js_delete_text'        => "«{1}» будет удалена безвозвратно. Отменить это действие нельзя.",
'js_btn_delete'         => "Удалить",
'js_btn_cancel'         => "Отмена",
'js_empty_title'        => "Новостей пока нет",
'js_empty_text'         => "Опубликуйте первую новость!",
'js_preview_empty'      => "Здесь появится предпросмотр…",
'js_spoiler'            => "Спойлер (нажмите, чтобы открыть)",
'js_loading_torrent'    => "Загружаем раздачу #{1}…",
'js_loading_preview'    => "Загружаем предпросмотр…",
'js_seeders'            => "Сиды: {1}",
'js_leechers'           => "Личи: {1}",
'js_preview_failed'     => "Не удалось загрузить предпросмотр.",

);
