<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['backupdb'] = array (

    // ── Page titles ──
    'title_main' => 'Резервные копии БД',
    'title_new' => 'Новая резервная копия БД',
    'title_delete' => 'Удаление резервной копии',
    'hero_sub_list' => 'Создание, скачивание и удаление SQL-копий базы данных трекера',
    'hero_sub_new' => 'Выберите таблицы и параметры, затем создайте копию',

    // ── Buttons ──
    'btn_new_backup' => 'Новая копия',
    'btn_back_to_list' => 'К списку',
    'btn_create' => 'Создать копию',
    'btn_create_first' => 'Создать первую копию',
    'btn_cancel' => 'Отмена',
    'btn_delete' => 'Удалить',
    'btn_confirm_delete' => 'Да, удалить',
    'btn_download' => 'Скачать',
    'btn_all' => 'Все',
    'btn_none' => 'Сбросить',
    'btn_go_back' => 'Назад',

    // ── Errors ──
    'err_title' => 'Ошибка',
    'err_unknown' => 'Неизвестная ошибка',
    'err_security_token' => 'Недействительный токен безопасности',

    // ── Flash messages ──
    'flash_no_file_dl' => 'Вы не указали резервную копию для скачивания',
    'flash_link_expired' => 'Ссылка недействительна или устарела. Попробуйте скачать файл ещё раз с этой страницы.',
    'flash_file_invalid' => 'Выбранный файл резервной копии недопустим или не существует',
    'flash_not_exist' => 'Указанная резервная копия не существует',
    'flash_deleted' => 'Резервная копия успешно удалена',
    'flash_not_deleted' => 'Не удалось удалить резервную копию',
    'flash_no_tables' => 'Вы не выбрали ни одной таблицы для резервного копирования',
    'flash_no_zlib' => 'Библиотека zlib для PHP не включена — создавать сжатые копии GZIP нельзя',
    'flash_created_title' => 'Резервная копия успешно создана',
    'flash_created_path' => 'Файл копии сохранён сюда:',

    // ── Alerts (alert_*_html keys are printed as-is: HTML allowed) ──
    'alert_readonly_dl_html' => 'Папка <code>admin/backup</code> недоступна для записи — копии можно только <strong>скачивать</strong>, сохранять на сервере нельзя.',
    'alert_readonly_list_html' => 'Папка <code>admin/backup</code> недоступна для записи — новые копии можно только скачивать.',
    'alert_no_zlib' => 'В PHP не включён zlib — сжатие GZIP недоступно.',

    // ── Delete confirmation / modal ──
    'confirm_title' => 'Удалить эту резервную копию?',
    'modal_title' => 'Удалить резервную копию?',
    'hint_irreversible' => 'Файл будет удалён с сервера. Это действие нельзя отменить.',
    'aria_close' => 'Закрыть',

    // ── Tables pane ──
    'sec_tables' => 'Таблицы',
    'lbl_selected' => 'Выбрано {1} из {2} · {3}',
    'ph_filter' => 'Фильтр таблиц…',
    'lbl_rows' => 'Строк: {1}',

    // ── Options ──
    'grp_filetype' => 'Тип файла',
    'grp_save' => 'Куда сохранить',
    'grp_contents' => 'Содержимое',
    'grp_maint' => 'Обслуживание',
    'opt_gzip_desc' => 'Сжатый · намного меньше',
    'opt_plain' => 'Обычный SQL',
    'opt_plain_desc' => 'Читаемый текстовый файл',
    'opt_method_download' => 'Скачать',
    'opt_method_download_desc' => 'Сразу на ваш компьютер',
    'opt_method_server' => 'Сервер',
    'opt_method_server_desc' => 'папка admin/backup',
    'opt_contents_full' => 'Полная',
    'opt_contents_full_desc' => 'Структура + данные',
    'opt_contents_structure' => 'Структура',
    'opt_contents_structure_desc' => 'Только таблицы',
    'opt_contents_data' => 'Данные',
    'opt_contents_data_desc' => 'Только строки',
    'opt_optimize' => 'Анализ и оптимизация',
    'opt_optimize_desc' => 'Сначала выполнит OPTIMIZE / ANALYZE для каждой выбранной таблицы',
    'hint_savebar' => 'Большие базы обрабатываются долго — не закрывайте страницу',

    // ── KPI tiles ──
    'kpi_backups' => 'Копий',
    'kpi_space' => 'Занято места',
    'kpi_latest' => 'Последняя',
    'kpi_folder' => 'Папка',
    'kpi_never' => 'никогда',
    'kpi_writable' => 'Запись разрешена',
    'kpi_readonly' => 'Только чтение',

    // ── Saved backups list ──
    'sec_saved' => 'Сохранённые копии',
    'th_file' => 'Файл',
    'th_size' => 'Размер',
    'th_created' => 'Создана',
    'tag_latest' => 'последняя',
    'empty_title' => 'Копий пока нет',
    'empty_hint' => 'Копии, сохранённые на сервере, появятся здесь.',
    'tip_download' => 'Скачать',
    'tip_delete' => 'Удалить',

    // ── JS strings (js_* → AGS_LANG, key without prefix) ──
    'js_working' => 'Выполняется…',
    'js_create_backup' => 'Создать копию',

);

?>
