<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['attachment_types'] = array (

	// ── Page titles (stdhead) ──
	'page_title' => 'Типы вложений',
	'page_title_add' => 'Типы вложений — Добавление',
	'page_title_edit' => 'Типы вложений — Редактирование',

	// ── Headings / subtitles ──
	'head_add' => 'Добавление типа вложения',
	'head_edit' => 'Редактирование типа вложения',
	'sub_form' => 'Тип файла, который пользователям разрешено прикреплять',
	'sub_list' => 'Расширения файлов, которые пользователи могут прикреплять к сообщениям и комментариям',

	// ── Buttons ──
	'btn_back' => 'Назад к списку',
	'btn_add' => 'Добавить тип',
	'btn_create' => 'Создать тип',
	'btn_save' => 'Сохранить изменения',
	'btn_cancel' => 'Отмена',
	'btn_delete' => 'Удалить',

	// ── Section headings ──
	'sec_file_type' => 'Тип файла',
	'sec_icon' => 'Иконка',
	'sec_behaviour' => 'Поведение',
	'sec_groups' => 'Доступно для групп',
	'sec_forums' => 'Доступно на форумах',
	'sec_all_types' => 'Все типы',

	// ── Field labels ──
	'lbl_name' => 'Название',
	'lbl_extension' => 'Расширение',
	'lbl_mime' => 'MIME-тип',
	'lbl_maxsize' => 'Максимальный размер',
	'lbl_icon_html' => 'HTML Font Awesome',
	'lbl_enabled' => 'Включён',
	'lbl_force_download' => 'Принудительное скачивание',
	'lbl_avatar_file' => 'Файл аватара',
	'lbl_upload_max' => 'Макс. загрузка',
	'lbl_post_max' => 'Макс. POST',

	// ── Hints (hint_mime and hint_icon contain HTML — printed as is) ──
	'hint_name' => 'Отображается пользователям, например «PDF-документ»',
	'hint_extension' => 'Без точки в начале',
	'hint_mime' => 'Что сервер отдаёт браузеру, например <code>application/pdf</code>',
	'hint_maxsize' => '0 — без собственного лимита (лимиты PHP всё равно действуют)',
	'hint_icon' => 'Например <code>&lt;i class="fas fa-file-pdf" style="color:#e74c3c;"&gt;&lt;/i&gt;</code>',
	'hint_enabled' => 'Пользователи могут загружать файлы этого типа',
	'hint_force_download' => 'Всегда скачивать, а не открывать в браузере',
	'hint_avatar_file' => 'Разрешить этот тип для аватаров',
	'hint_multi_select' => 'Удерживайте Ctrl (⌘ на Mac), чтобы выбрать несколько',

	// ── Placeholders ──
	'ph_name' => 'напр. PDF-документ',
	'ph_extension' => 'pdf',
	'ph_mime' => 'application/pdf',
	'ph_maxsize' => '0 = без ограничений',
	'ph_filter' => 'Фильтр по расширению, названию или MIME…',

	// ── Sizes / units ──
	'unit_kb' => 'КБ',
	'unit_mb' => 'МБ',
	'size_unlimited' => 'Без ограничений',
	'pill_php_limit' => 'PHP {1}: {2}',

	// ── Options (all / selected / none) ──
	'opt_all_groups' => 'Все группы',
	'opt_all_forums' => 'Все форумы',
	'opt_selected' => 'Выбранные',
	'opt_none' => 'Нет',

	// ── Icon presets ──
	'preset_pdf' => 'PDF',
	'preset_image' => 'Изображение',
	'preset_archive' => 'Архив',
	'preset_word' => 'Word',
	'preset_excel' => 'Excel',
	'preset_ppt' => 'PowerPoint',
	'preset_video' => 'Видео',
	'preset_audio' => 'Аудио',
	'preset_text' => 'Текст',
	'preset_code' => 'Код',

	// ── Stat cards ──
	'stat_total' => 'Всего типов',
	'stat_enabled' => 'Включено',
	'stat_disabled' => 'Выключено',
	'stat_avg_limit' => 'Средний лимит',

	// ── Table ──
	'th_type' => 'Тип',
	'th_mime' => 'MIME',
	'th_status' => 'Статус',
	'th_max_size' => 'Макс. размер',
	'th_access' => 'Доступ',
	'th_flags' => 'Флаги',
	'th_actions' => 'Действия',
	'status_enabled' => 'Включён',
	'status_disabled' => 'Выключен',
	'scope_all_groups' => 'Все группы',
	'scope_none_groups' => 'Нет групп',
	'scope_n_groups' => 'Групп: {1}',
	'scope_all_forums' => 'Все форумы',
	'scope_none_forums' => 'Нет форумов',
	'scope_n_forums' => 'Форумов: {1}',
	'empty_title' => 'Типов вложений пока нет',
	'empty_text' => 'Добавьте первый тип файла, который пользователям разрешено прикреплять.',
	'no_match' => 'На этой странице ничего не найдено',

	// ── Tooltips (title) ──
	'tip_force_download' => 'Принудительное скачивание',
	'tip_opens_browser' => 'Открывается в браузере',
	'tip_avatar_yes' => 'Разрешён для аватаров',
	'tip_avatar_no' => 'Не для аватаров',
	'tip_edit' => 'Изменить',
	'tip_enable' => 'Включить',
	'tip_disable' => 'Выключить',
	'tip_delete' => 'Удалить',

	// ── Delete modal ──
	'modal_delete_title' => 'Удаление типа вложения',
	'modal_delete_q' => 'Удалить {1}?',
	'modal_delete_text' => 'Пользователи больше не смогут загружать файлы этого типа. Уже прикреплённые файлы останутся на месте.',
	'aria_close' => 'Закрыть',

	// ── Errors ──
	'err_no_permission' => 'Ошибка! У вас нет прав для доступа к этой странице.',
	'err_token' => 'Недействительный токен безопасности',
	'err_no_extension' => 'Вы не указали расширение файла для этого типа вложения',
	'err_no_mime' => 'Вы не указали MIME-тип для этого типа вложения',

	// ── Flash messages ──
	'flash_created' => 'Тип вложения создан.',
	'flash_updated' => 'Тип вложения обновлён.',
	'flash_deleted' => 'Тип вложения удалён.',
	'flash_invalid' => 'Такого типа вложения не существует.',
	'flash_enabled' => 'Тип вложения включён.',
	'flash_disabled' => 'Тип вложения выключен.',

);
?>
