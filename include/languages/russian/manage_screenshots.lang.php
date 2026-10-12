<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['manage_screenshots'] = array (

    // ── Page titles (stdhead) ──
    'title_list'                => 'Управление скриншотами',
    'title_add'                 => 'Добавление скриншотов',
    'title_edit'                => 'Редактирование скриншота',

    // ── Page headers ──
    'sec_list_title'            => 'Управление скриншотами',
    'sec_list_sub'              => 'Загрузка, замена и удаление скриншотов раздач',
    'sec_add_title'             => 'Добавить скриншоты',
    'sec_add_sub'               => 'Прикрепите к раздаче одно или несколько изображений',
    'sec_edit_title'            => 'Скриншот #{1}: редактирование',
    'sec_edit_sub'              => 'Привяжите скриншот к другой раздаче или замените файл',

    // ── KPI tiles ──
    'kpi_total'                 => 'Всего скриншотов',
    'kpi_torrents'              => 'Раздач со скриншотами',
    'kpi_day'                   => 'Загружено за 24 часа',
    'kpi_week'                  => 'Загружено за 7 дней',

    // ── Filter ──
    'ph_filename'               => 'Имя файла',
    'aria_search'               => 'Поиск по имени файла',
    'ph_torrent_id'             => 'ID раздачи',
    'btn_filter'                => 'Найти',
    'btn_reset'                 => 'Сбросить',
    'lbl_found'                 => 'Найдено: {1}',

    // ── Empty states ──
    'empty_filter_title'        => 'По этому фильтру ничего не найдено',
    'empty_filter_text'         => 'Попробуйте другое имя файла или ID раздачи.',
    'btn_reset_filter'          => 'Сбросить фильтр',
    'empty_title'               => 'Скриншотов пока нет',
    'empty_text'                => 'Загрузите первые скриншоты к раздаче.',
    'notfound_title'            => 'Скриншот не найден',
    'notfound_text'             => 'Возможно, он уже удалён.',

    // ── Screenshot cards ──
    'lbl_torrent'               => 'Раздача #{1}',
    'aria_select'               => 'Выбрать скриншот #{1}',
    'aria_view'                 => 'Открыть скриншот #{1}',
    'alt_screenshot'            => 'Скриншот #{1}',
    'alt_preview'               => 'Превью',
    'tip_open_original'         => 'Открыть оригинал',

    // ── Buttons ──
    'btn_upload_screenshots'    => 'Загрузить скриншоты',
    'btn_edit'                  => 'Изменить',
    'btn_delete'                => 'Удалить',
    'btn_cancel'                => 'Отмена',
    'btn_close'                 => 'Закрыть',
    'btn_back'                  => 'К списку',
    'btn_select_all'            => 'Выбрать все',
    'btn_delete_selected'       => 'Удалить выбранные',
    'btn_choose_files'          => 'Выбрать файлы',
    'btn_clear_all'             => 'Очистить',
    'btn_upload'                => 'Загрузить',
    'btn_save_changes'          => 'Сохранить изменения',
    'btn_save_screenshots'      => 'Сохранить скриншоты',

    // ── Bottom bars ──
    'lbl_selected'              => 'Выбрано: {1}',
    'lbl_selected_files'        => 'Выбрано файлов: {1}',

    // ── Delete modals ──
    'pane_confirm_delete'       => 'Подтверждение удаления',
    'lbl_delete_single_q'       => 'Удалить скриншот?',
    'lbl_loading'               => 'Загрузка...',
    'warn_undo'                 => 'Это действие необратимо.',
    'warn_single_text'          => 'Файл и запись о нём будут удалены.',
    'lbl_delete_mass_q'         => 'Удалить выбранные скриншоты ({1} шт.)?',
    'lbl_delete_mass_text'      => 'Файлы и записи всех выбранных скриншотов будут удалены.',
    'lbl_selected_screens'      => 'Выбранные скриншоты:',
    'warn_mass_text'            => 'Восстановить удалённые скриншоты будет нельзя.',

    // ── Upload modal ──
    'lbl_torrent_id'            => 'ID раздачи',
    'ph_torrent_example'        => 'Например, 1542',
    'lbl_drop_title'            => 'Перетащите скриншоты сюда',
    'hint_drop_formats'         => 'JPG, PNG, GIF или WEBP, до 10 МБ каждый',
    'lbl_uploading'             => 'Загрузка...',

    // ── Edit modal ──
    'pane_edit'                 => 'Редактирование скриншота',
    'lbl_replace_optional'      => 'Заменить файл (необязательно)',
    'lbl_saving'                => 'Сохранение...',

    // ── Add page ──
    'lbl_files'                 => 'Файлы скриншотов',
    'hint_allowed_multi'        => 'Допустимые форматы: {1}. Можно выбрать сразу несколько файлов.',
    'hint_preview_here'         => 'Здесь появится превью выбранных изображений',

    // ── Edit page ──
    'lbl_replace_file'          => 'Заменить файл',
    'hint_keep_file'            => 'Оставьте пустым, чтобы сохранить {1}',

    // ── Formats ──
    'fmt_date'                  => 'd.m.Y H:i',

    // ── Messages: success ──
    'flash_mass_deleted'        => 'Удалено: {1}',
    'flash_mass_deleted_errors' => 'Удалено: {1}, ошибок: {2}',
    'flash_uploaded'            => 'Скриншоты загружены: {1} шт.',
    'flash_uploaded_partial'    => 'Загружено: {1}, ошибки: {2}',
    'flash_uploaded_with_errors'=> 'Скриншоты загружены: {1} шт. Ошибки: {2}',
    'flash_updated'             => 'Скриншот обновлён',
    'flash_deleted'             => 'Скриншот удалён',

    // ── Messages: errors ──
    'err_csrf'                  => 'Неверный ключ безопасности. Обновите страницу и попробуйте снова',
    'err_none_selected'         => 'Не выбрано ни одного скриншота',
    'err_mass_failed'           => 'Не удалось удалить скриншоты',
    'err_torrent_required'      => 'Укажите корректный ID раздачи',
    'err_torrent_missing'       => 'Раздачи с ID {1} не существует',
    'err_no_files'              => 'Файлы не загружены',
    'err_no_file'               => 'Файл не выбран или произошла ошибка загрузки',
    'err_upload_failed'         => 'Не удалось загрузить',
    'err_not_found'             => 'Скриншот не найден',
    'err_format_allowed'        => 'Недопустимый формат. Разрешены: {1}',
    'err_not_image'             => 'Файл не является изображением',
    'err_file_upload_failed'    => 'Не удалось загрузить файл',
    'err_corrupt'               => 'Файл повреждён или не является изображением',
    'err_post_required'         => 'Это действие выполняется только POST-запросом',

    // ── Messages: per-file errors ({1} = file name) ──
    'err_f_upload'              => '{1}: ошибка загрузки',
    'err_f_upload_code'         => '{1}: ошибка загрузки (код {2})',
    'err_f_too_large'           => '{1}: слишком большой файл (максимум 10 МБ)',
    'err_f_format'              => '{1}: недопустимый формат',
    'err_f_format_allowed'      => '{1}: недопустимый формат. Разрешены: {2}',
    'err_f_not_image'           => '{1}: не является изображением',
    'err_f_save'                => '{1}: не удалось сохранить',
    'err_f_corrupt'             => '{1}: файл повреждён или содержит некорректные данные',

    // ── JS: general ──
    'js_locale'                 => 'ru-RU',
    'js_server_down'            => 'Сервер не отвечает.',
    'js_no_image'               => 'Нет изображения',
    'js_screenshot'             => 'Скриншот',

    // ── JS: single delete ──
    'js_confirm_deletion'       => 'Подтверждение удаления',
    'js_delete_q'               => 'Удалить скриншот?',
    'js_this_screenshot'        => 'этот скриншот',
    'js_quoted'                 => '«{1}»',
    'js_loading'                => 'Загрузка...',
    'js_kb'                     => '{1} КБ',
    'js_deleting'               => 'Удаление...',
    'js_deleted_ok'             => 'Скриншот удалён.',
    'js_delete_failed'          => 'Не удалось удалить скриншот.',
    'js_delete_error'           => 'При удалении скриншота произошла ошибка.',

    // ── JS: mass delete ──
    'js_no_selection_title'     => 'Ничего не выбрано',
    'js_no_selection_text'      => 'Выберите хотя бы один скриншот для удаления.',
    'js_select_all'             => 'Выбрать все',
    'js_deselect_all'           => 'Снять выделение',
    'js_mass_error'             => 'При удалении скриншотов произошла ошибка',

    // ── JS: upload modal (plural forms per Intl.PluralRules) ──
    'js_files_one'              => '{1} файл',
    'js_files_few'              => '{1} файла',
    'js_files_many'             => '{1} файлов',
    'js_files_other'            => '{1} файла',
    'js_upload_failed'          => 'Не удалось загрузить.',

    // ── JS: edit modal ──
    'js_keep_file'              => 'Оставьте пустым, чтобы сохранить: {1}',
    'js_torrent_required'       => 'Укажите ID раздачи',
    'js_update_failed'          => 'Не удалось сохранить изменения.',
);
?>
