<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');
$language['country'] = array (

    // ── Page titles / subtitles ──
    'pane_title_list'   => 'Страны',
    'pane_title_add'    => 'Добавить страну',
    'pane_title_edit'   => 'Редактирование страны',
    'sub_list'          => 'Список, из которого пользователи выбирают страну в профиле; флаг показывается рядом с ником.',
    'sub_add'           => 'Файл флага должен быть заранее загружен в папку флагов на сервере.',
    'sub_edit'          => 'Переименование изменится у всех пользователей, выбравших эту страну.',

    // ── Buttons ──
    'btn_add'           => 'Добавить страну',
    'btn_back'          => 'Все страны',
    'btn_cancel'        => 'Отмена',
    'btn_save'          => 'Сохранить',

    // ── Statistics cards ──
    'kpi_countries'     => 'Стран',
    'kpi_flag_files'    => 'Файлов флагов',
    'kpi_unused'        => 'Свободных флагов',
    'kpi_missing'       => 'Нет файла флага',

    // ── Section headings ──
    'sec_all'           => 'Все страны',
    'sec_unused'        => 'Свободные флаги',
    'sec_preview'       => 'Так это увидят пользователи',

    // ── Table ──
    'th_flag'           => 'Флаг',
    'th_name'           => 'Название',
    'th_file'           => 'Файл',
    'th_id'             => 'ID',
    'th_actions'        => 'Действия',
    'tag_missing'       => 'Нет файла',
    'tip_flag_missing'  => 'Файл флага не найден',
    'tip_edit'          => 'Редактировать: {1}',
    'tip_delete'        => 'Удалить: {1}',

    // ── Form labels ──
    'lbl_name'          => 'Название страны',
    'lbl_flag'          => 'Флаг',
    'lbl_no_flag_picked' => 'Флаг не выбран',
    'lbl_country_id'    => 'ID страны: {1}',
    'ph_name'           => 'Болгария',
    'ph_filter'         => 'Поиск по названию или файлу',
    'ph_find_flag'      => 'Найти файл флага',
    'aria_filter'       => 'Фильтр по странам',
    'aria_find_flag'    => 'Найти файл флага',
    'aria_flag_group'   => 'Флаг',

    // ── Hints ──
    'hint_unused'       => 'Нажмите на флаг, чтобы добавить страну с ним.',

    // ── Empty states ──
    'empty_title'       => 'Стран пока нет',
    'empty_text'        => 'Добавьте первую, и пользователи смогут выбрать её в профиле.',
    'empty_no_match'    => 'Ни одна страна не подходит под фильтр.',
    'empty_no_flag_match' => 'Подходящих файлов флагов нет.',
    'empty_no_flag_files' => 'В папке {1} нет изображений флагов. Сначала загрузите файлы.',

    // ── Validation errors ──
    'err_name_empty'    => 'Введите название страны.',
    'err_name_long'     => 'Название длиннее {1} символов.',
    'err_flag_empty'    => 'Выберите флаг.',
    'err_flag_missing'  => 'Такого файла флага нет в папке флагов.',
    'err_duplicate'     => 'Страна «{1}» уже есть в списке.',

    // ── Flash messages ──
    'flash_security'    => 'Проверка безопасности не пройдена. Попробуйте ещё раз.',
    'flash_unknown'     => 'Неизвестное действие.',
    'flash_not_found'   => 'Страна не найдена.',
    'flash_saved'       => 'Страна «{1}» сохранена.',
    'flash_added'       => 'Страна «{1}» добавлена.',
    'flash_deleted'     => 'Страна «{1}» удалена.',

    // ── JS strings (exported to AGS_LANG without the js_ prefix) ──
    'js_confirm_title'  => 'Удалить «{1}»?',
    'js_confirm_text'   => 'У пользователей, выбравших эту страну, не будет флага, пока они не выберут другую.',
    'js_confirm_btn'    => 'Удалить',
    'js_cancel_btn'     => 'Отмена',
    'js_default_name'   => 'эту страну',
    'js_preview_name'   => 'Название страны',
);
?>
