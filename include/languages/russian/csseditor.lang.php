<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['csseditor'] = array (
	// ── Page ──
	'page_title'             => 'Редактор CSS',
	'sec_editor'             => 'Расширенный редактор CSS',
	'lbl_file_select'        => 'Файл стилей',
	'lbl_preview_toggle'     => 'Превью',

	// ── Snippets ──
	'opt_snip_reset'         => 'Сброс',
	'opt_snip_flex_center'   => 'Flex по центру',
	'opt_snip_grid_layout'   => 'Сетка (Grid)',
	'opt_snip_media_query'   => 'Медиазапрос',
	'opt_snip_animation'     => 'Анимация',
	'opt_snip_transition'    => 'Переход',
	'opt_snip_transform'     => 'Трансформация',
	'opt_snip_shadow'        => 'Тень',

	// ── Toolbar ──
	'lbl_backup'             => 'Бэкап',
	'lbl_minify'             => 'Сжать',
	'btn_search'             => 'Поиск',
	'btn_format'             => 'Форматировать',
	'btn_save'               => 'Сохранить',
	'btn_reload'             => 'Перезагрузить',

	// ── Preview ──
	'sec_preview'            => 'Предпросмотр стилей',
	'sec_preview_basic'      => 'Базовые элементы',
	'sec_preview_typography' => 'Типографика',
	'lbl_demo_primary'       => 'Основная',
	'lbl_demo_secondary'     => 'Вторичная',
	'lbl_demo_success'       => 'Успех',
	'lbl_demo_warning'       => 'Пример предупреждения',
	'lbl_demo_card_title'    => 'Пример карточки',
	'lbl_demo_card_text'     => 'Пример текста внутри карточки.',
	'lbl_demo_card_btn'      => 'Кнопка карточки',
	'lbl_demo_h1'            => 'Заголовок h1',
	'lbl_demo_h2'            => 'Заголовок h2',
	// Output as-is (HTML allowed)
	'lbl_demo_paragraph'     => 'Обычный абзац с <strong>жирным</strong> и <em>курсивом</em>.',
	'lbl_demo_list_1'        => 'Пункт списка 1',
	'lbl_demo_list_2'        => 'Пункт списка 2',
	'btn_refresh_preview'    => 'Обновить превью',

	// ── Tabs ──
	'pane_backups'           => 'Бэкапы',
	'pane_stats'             => 'Статистика',
	'pane_help'              => 'Справка',

	// ── Backups ──
	'sec_backups'            => 'Бэкапы:',
	'btn_refresh_backups'    => 'Обновить',
	'th_date'                => 'Дата',
	'th_size'                => 'Размер',
	'th_action'              => 'Действие',
	'tip_view'               => 'Просмотр',
	'tip_restore'            => 'Восстановить',
	'lbl_no_backups'         => 'Бэкапов пока нет',

	// ── Statistics ──
	'sec_css_stats'          => 'Статистика CSS:',
	'lbl_selectors'          => 'Селекторы',
	'lbl_properties'         => 'Свойства',
	'lbl_media_queries'      => 'Медиазапросы',
	'sec_file_size'          => 'Размер файла:',
	'lbl_minified'           => 'Сжатый:',
	'lbl_original'           => 'Исходный:',

	// ── Help ──
	'sec_shortcuts'          => 'Горячие клавиши:',
	'hint_kb_search'         => 'Поиск',
	'hint_kb_replace'        => 'Замена',
	'hint_kb_save'           => 'Сохранить',
	'hint_kb_autocomplete'   => 'Автодополнение',
	'hint_kb_format'         => 'Форматировать',
	'hint_kb_comment'        => 'Закомментировать / раскомментировать',
	'sec_templates'          => 'Шаблоны:',
	'hint_templates'         => 'Кнопки над редактором вставляют типовые CSS-конструкции.',

	// ── Backup modal ──
	'sec_backup_modal'       => 'Просмотр бэкапа',
	'btn_close'              => 'Закрыть',
	'btn_load_into_editor'   => 'Загрузить в редактор',

	// ── Flash ──
	'flash_saved_backup'     => 'Файл сохранён, бэкап создан!',
	'flash_saved'            => 'Файл сохранён!',

	// ── Errors ──
	'err_page'               => 'Ошибка: {1}',
	'err_invalid_token'      => 'Недействительный токен безопасности. Перезагрузите страницу.',
	'err_unknown_action'     => 'Неизвестное действие: {1}',
	'err_file_not_found'     => 'Файл не найден: {1}',
	'err_not_writable'       => 'Нет прав на запись: {1}',
	'err_empty_content'      => 'Содержимое не может быть пустым',
	'err_save_failed'        => 'Не удалось сохранить файл',
	'err_invalid_filename'   => 'Недопустимое имя файла',
	'err_access_denied'      => 'Доступ запрещён',
	'err_access_denied_file' => 'Доступ запрещён: {1}',
	'err_backup_not_found'   => 'Файл бэкапа не найден',
	'err_backup_read_failed' => 'Не удалось прочитать файл бэкапа',
	'err_backup_create_failed' => 'Не удалось создать бэкап',

	// ── JS: toasts ──
	'js_formatted'           => 'Код отформатирован',
	'js_backup_loaded'       => 'Бэкап загружен в редактор',
	'js_content_loaded'      => 'Содержимое загружено в редактор',
	'js_list_updated'        => 'Список обновлён',
	'js_editor_empty'        => 'Редактор пуст',
	'js_styles_applied'      => 'Стили применены',
	'js_snippet_added'       => 'Сниппет вставлен',
	'js_no_backups'          => 'Бэкапов пока нет',
	'js_view'                => 'Просмотр',
	'js_restore'             => 'Восстановить',

	// ── JS: CodeMirror search dialog ──
	'js_cm_search'           => 'Найти:',
	'js_cm_regex_hint'       => '(для регулярного выражения используйте /re/)',
	'js_cm_replace'          => 'Заменить:',
	'js_cm_replace_all'      => 'Заменить все:',
	'js_cm_with'             => 'На:',
	'js_cm_replace_confirm'  => 'Заменить?',
	'js_cm_yes'              => 'Да',
	'js_cm_no'               => 'Нет',
	'js_cm_all'              => 'Все',
	'js_cm_stop'             => 'Стоп',
);
?>
