<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['spam'] = array (

	// ── Header ──
	'pane_title' => 'Личные сообщения',
	'pane_subtitle' => 'Разбор персоналом переписки пользователей и жалоб на спам',

	// ── KPI ──
	'kpi_total' => 'Всего сообщений',
	'kpi_unread' => 'Не прочитано',
	'kpi_last24' => 'За последние 24 часа',
	'kpi_matching' => 'Подходит под фильтры',
	'kpi_shown_all' => 'Показано (без фильтров)',

	// ── Filters ──
	'lbl_search' => 'Поиск',
	'ph_search' => 'Тема или текст сообщения',
	'lbl_sender_uid' => 'UID отправителя',
	'lbl_recipient_uid' => 'UID получателя',
	'ph_any' => 'Любой',
	'lbl_status' => 'Статус',
	'opt_status_all' => 'Все статусы',
	'opt_status_unread' => 'Непрочитанные',
	'opt_status_read' => 'Прочитанные / отвеченные',
	'btn_filter' => 'Применить',
	'btn_reset' => 'Сбросить',
	'lbl_active' => 'Фильтры:',
	'tip_remove' => 'Убрать',
	'tag_search' => '«{1}»',
	'tag_from_uid' => 'От UID {1}',
	'tag_to_uid' => 'Кому UID {1}',

	// ── Message statuses ──
	'status_unread' => 'Не прочитано',
	'status_read' => 'Прочитано',
	'status_replied' => 'Отвечено',
	'status_forwarded' => 'Переслано',
	'status_other' => 'Статус {1}',

	// ── Table ──
	'th_sender' => 'Отправитель',
	'th_recipient' => 'Получатель',
	'th_subject' => 'Тема',
	'th_date' => 'Дата',
	'th_status' => 'Статус',
	'th_ip' => 'IP',
	'tip_filter_sender' => 'Показать все сообщения этого отправителя',
	'tip_filter_recipient' => 'Показать все сообщения этому получателю',
	'lbl_no_subject' => '(без темы)',
	'btn_view' => 'Открыть',
	'name_system' => 'Система',
	'name_deleted' => 'Удалён #{1}',
	'name_deleted_user' => 'Удалённый пользователь #{1}',

	// ── Empty state ──
	'msg_empty_title' => 'Сообщения не найдены',
	'msg_empty_filters' => 'Попробуйте изменить или сбросить фильтры.',
	'msg_empty_none' => 'Личных сообщений пока нет.',

	// ── Modal and toast ──
	'lbl_modal_title' => 'Сообщение',
	'aria_close' => 'Закрыть',
	'btn_close' => 'Закрыть',
	'lbl_toast_done' => 'Готово',

	// ── Message view (spam_message.php) ──
	'err_access_denied' => 'Доступ запрещён. Только для персонала.',
	'err_invalid_id' => 'Неверный ID сообщения',
	'err_not_found' => 'Сообщение не найдено',
	'role_sender' => 'Отправитель',
	'role_recipient' => 'Получатель',
	'tip_sent_date' => 'Дата отправки',
	'tip_sender_ip' => 'IP-адрес отправителя',
	'tip_message_id' => 'ID сообщения',
	'tip_message_length' => 'Длина сообщения',
	'lbl_chars' => '{1} симв.',
	'tab_rendered' => 'Отображение',
	'tab_raw' => 'Исходный текст',
	'btn_copy' => 'Копировать',
	'tip_copy' => 'Скопировать исходный текст в буфер обмена',
	'tip_download' => 'Скачать как .txt',

	// ── JavaScript strings (spam.js) ──
	'js_no_subject' => '(без темы)',
	'js_loading' => 'Загрузка сообщения…',
	'js_load_failed' => 'Не удалось загрузить сообщение. {1}',
	'js_copied' => 'Скопировано в буфер обмена',
	'js_saved' => 'Сохранено: {1}',
);
?>
