<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['phpeditor'] = array (
    // ── Page ──
    'page_title'            => 'Редактор PHP',
    'hdr_title'             => 'Редактор PHP',
    'aria_select_file'      => 'Выбор файла',

    // ── File info bar ──
    'lbl_info_lines'        => 'Строк: {1}',
    'lbl_read_only'         => 'Только чтение',

    // ── Editor controls ──
    'lbl_backup'            => 'Бэкап',
    'lbl_syntax_check'      => 'Проверка синтаксиса',
    'btn_search'            => 'Поиск',
    'btn_format'            => 'Формат',
    'btn_save'              => 'Сохранить',
    'btn_reload'            => 'Обновить',

    // ── Tabs ──
    'tab_backups'           => 'Бэкапы',
    'tab_stats'             => 'Статистика',
    'tab_help'              => 'Справка',

    // ── Backups pane ──
    'sec_backups_for'       => 'Бэкапы файла {1}',
    'btn_refresh_backups'   => 'Обновить',
    'th_date'               => 'Дата',
    'th_size'               => 'Размер',
    'th_action'             => 'Действия',
    'title_view'            => 'Просмотр',
    'title_restore'         => 'Восстановить',
    'lbl_no_backups'        => 'Бэкапов пока нет',

    // ── Statistics pane ──
    'sec_file_stats'        => 'Статистика файла:',
    'lbl_lines'             => 'Строк',
    'lbl_functions'         => 'Функций',
    'lbl_classes'           => 'Классов',
    'lbl_comments'          => 'Комментариев',
    'sec_file_size'         => 'Размер файла:',
    'lbl_current_size'      => 'Текущий:',
    'lbl_original_size'     => 'Исходный:',

    // ── Help pane ──
    'sec_shortcuts'         => 'Горячие клавиши:',
    'hint_key_save'         => 'Сохранить файл',
    'hint_key_search'       => 'Поиск',
    'hint_key_replace'      => 'Найти и заменить',
    'hint_key_goto'         => 'Перейти к строке',
    'hint_key_autocomplete' => 'Автодополнение',
    'hint_key_format'       => 'Форматировать код',
    'hint_key_comment'      => 'Закомментировать / раскомментировать',
    'hint_key_undo'         => 'Отменить',
    'hint_key_redo'         => 'Повторить',
    'sec_file_types'        => 'Поддерживаемые типы файлов:',
    'sec_notes'             => 'Примечания:',
    'hint_notes'            => 'PHP-файлы проверяются на синтаксис перед сохранением. Перед крупными правками обязательно делайте бэкап.',

    // ── Backup preview modal ──
    'modal_backup_title'    => 'Просмотр бэкапа',
    'btn_close'             => 'Закрыть',
    'btn_load_into_editor'  => 'Загрузить в редактор',

    // ── Flash messages (AJAX) ──
    'flash_saved_backup'    => 'Файл сохранён, бэкап создан!',
    'flash_saved'           => 'Файл сохранён!',

    // ── Errors ──
    'err_invalid_token'     => 'Неверный ключ безопасности. Обновите страницу.',
    'err_unknown_action'    => 'Неизвестное действие: {1}',
    'err_file_not_found'    => 'Файл не найден: {1}',
    'err_not_writable'      => 'Нет прав на запись: {1}',
    'err_empty_content'     => 'Содержимое не может быть пустым',
    'err_php_syntax'        => 'Синтаксическая ошибка PHP: {1}',
    'err_save_failed'       => 'Не удалось сохранить файл',
    'err_backup_failed'     => 'Не удалось создать бэкап',
    'err_invalid_filename'  => 'Недопустимое имя файла',
    'err_backup_not_found'  => 'Файл бэкапа не найден',
    'err_backup_read_failed'=> 'Не удалось прочитать файл бэкапа',
    'err_access_denied'     => 'Доступ запрещён: {1}',
    'err_page'              => 'Ошибка: {1}',

    // ── JS strings ──
    'js_formatted'          => 'Код отформатирован',
    'js_backup_loaded'      => 'Бэкап загружен в редактор',
    'js_content_loaded'     => 'Содержимое загружено в редактор',
    'js_list_refreshed'     => 'Список обновлён',
    'js_no_backups'         => 'Бэкапов пока нет',
    'js_unbalanced_braces'  => 'Непарные {}',
    'js_unbalanced_parens'  => 'Непарные ()',
    'js_unbalanced_brackets'=> 'Непарные []',
    'js_syntax_ok'          => 'OK',
    'js_info_lines'         => 'Строк: {1}',
);
?>
